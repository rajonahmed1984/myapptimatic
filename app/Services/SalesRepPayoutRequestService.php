<?php

namespace App\Services;

use App\Models\CommissionAuditLog;
use App\Models\CommissionPayoutRequest;
use App\Models\PaymentMethod;
use App\Models\SalesRepresentative;
use App\Support\SystemLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sales reps ask for the commission available to them; an admin pays it,
 * recording how, or declines it. A rep has at most one request waiting.
 */
class SalesRepPayoutRequestService
{
    public function __construct(
        private CommissionService $commissions,
        private SalesRepStatementService $statements,
    ) {
    }

    /**
     * What the rep can ask for now.
     *
     * `available` is commission clients have paid for, on finished work, not
     * yet received. When the rep holds more than they have earned it is the
     * (negative) balance instead, and nothing can be requested.
     *
     * @return array{available: float, balance: float, on_hold: float, pending_amount: float, can_request: bool, reason: string|null}
     */
    public function availability(SalesRepresentative $rep): array
    {
        $statement = $this->statements->forRep($rep->id);
        $balance = (float) $statement['balance'];
        $payable = (float) ($this->commissions->computeRepBalance($rep->id)['payable_balance'] ?? 0);
        $pending = $this->pendingRequest($rep);

        $available = $balance < -0.009 ? $balance : $payable;
        // Client-paid commission on work not yet complete: payable later.
        $onHold = $balance > 0.009 ? round(max(0, $balance - $payable), 2) : 0.0;

        $reason = match (true) {
            $balance < -0.009 => 'You have received more than the commission clients have paid for, so there is nothing to request.',
            $pending !== null => 'You already have a payout request waiting for the admin.',
            $payable <= 0.009 => 'Nothing is available yet. Commission becomes available once clients pay and the work is complete.',
            default => null,
        };

        return [
            'available' => round($available, 2),
            'balance' => round($balance, 2),
            'on_hold' => $onHold,
            'pending_amount' => $pending ? (float) $pending->amount : 0.0,
            'can_request' => $reason === null,
            'reason' => $reason,
        ];
    }

    public function pendingRequest(SalesRepresentative $rep): ?CommissionPayoutRequest
    {
        return CommissionPayoutRequest::query()
            ->where('sales_representative_id', $rep->id)
            ->where('status', CommissionPayoutRequest::STATUS_PENDING)
            ->latest('id')
            ->first();
    }

    public function submit(SalesRepresentative $rep, float $amount, ?string $note = null): CommissionPayoutRequest
    {
        $availability = $this->availability($rep);
        $amount = round($amount, 2);

        if (! $availability['can_request']) {
            throw new RuntimeException((string) $availability['reason']);
        }

        if ($amount <= 0 || $amount > $availability['available'] + 0.009) {
            throw new RuntimeException(sprintf('You can request up to %s.', number_format($availability['available'], 2)));
        }

        $request = CommissionPayoutRequest::create([
            'sales_representative_id' => $rep->id,
            'amount' => $amount,
            'currency' => 'BDT',
            'note' => $note,
            'status' => CommissionPayoutRequest::STATUS_PENDING,
        ]);

        SystemLogger::write('activity', 'Sales rep requested a payout.', [
            'sales_representative_id' => $rep->id,
            'payout_request_id' => $request->id,
            'amount' => $amount,
        ]);

        try {
            app(AdminNotificationService::class)->sendPayoutRequested($request);
        } catch (\Throwable) {
            // A failed email must not lose the request.
        }

        return $request;
    }

    public function cancel(CommissionPayoutRequest $request): void
    {
        $this->assertPending($request);

        $request->update([
            'status' => CommissionPayoutRequest::STATUS_CANCELLED,
            'processed_at' => now(),
        ]);
    }

    /**
     * Pay the request (or less, if the admin lowers it) and record the method.
     */
    public function approve(
        CommissionPayoutRequest $request,
        float $amount,
        string $payoutMethod,
        ?string $reference,
        ?string $adminNote,
        ?int $actorId
    ): CommissionPayoutRequest {
        $this->assertPending($request);
        $amount = round($amount, 2);

        if ($amount <= 0 || $amount > (float) $request->amount + 0.009) {
            throw new RuntimeException('The amount paid must be more than 0 and no more than was requested.');
        }

        if (! in_array($payoutMethod, PaymentMethod::allowedCommissionPayoutCodes(), true)) {
            throw new RuntimeException('Choose how the payout was made.');
        }

        return DB::transaction(function () use ($request, $amount, $payoutMethod, $reference, $adminNote, $actorId) {
            // Checks the amount against what is payable right now.
            $payout = $this->commissions->payAmount(
                (int) $request->sales_representative_id,
                $amount,
                $payoutMethod,
                $reference,
                $adminNote ?: 'Payout request #'.$request->id,
                (string) $request->currency
            );

            $request->update([
                'status' => CommissionPayoutRequest::STATUS_PAID,
                'commission_payout_id' => $payout->id,
                'paid_amount' => $amount,
                'payout_method' => $payoutMethod,
                'reference' => $reference,
                'admin_note' => $adminNote,
                'processed_by' => $actorId,
                'processed_at' => now(),
            ]);

            CommissionAuditLog::create([
                'sales_representative_id' => $request->sales_representative_id,
                'commission_payout_id' => $payout->id,
                'action' => 'payout_request_paid',
                'status_from' => CommissionPayoutRequest::STATUS_PENDING,
                'status_to' => CommissionPayoutRequest::STATUS_PAID,
                'description' => 'Payout request paid.',
                'metadata' => [
                    'payout_request_id' => $request->id,
                    'requested' => (float) $request->amount,
                    'paid' => $amount,
                    'payout_method' => $payoutMethod,
                ],
                'created_by' => $actorId,
            ]);

            return $request->fresh();
        });
    }

    public function reject(CommissionPayoutRequest $request, ?string $adminNote, ?int $actorId): void
    {
        $this->assertPending($request);

        $request->update([
            'status' => CommissionPayoutRequest::STATUS_REJECTED,
            'admin_note' => $adminNote,
            'processed_by' => $actorId,
            'processed_at' => now(),
        ]);
    }

    private function assertPending(CommissionPayoutRequest $request): void
    {
        if (! $request->isPending()) {
            throw new RuntimeException('This payout request has already been handled.');
        }
    }
}
