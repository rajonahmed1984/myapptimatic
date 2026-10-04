<?php

namespace App\Support;

use App\Http\Controllers\SalesRep\PayoutController;
use App\Models\CommissionPayoutRequest;

/**
 * One payout request as the admin pages show it.
 */
class PayoutRequestPresenter
{
    /**
     * @param  array{available: float, balance: float}|null  $availability  the rep's, for a waiting request
     * @param  array<string, string>|null  $methodNames  payout method code => name
     * @return array<string, mixed>
     */
    public static function adminRow(CommissionPayoutRequest $item, ?array $availability = null, ?array $methodNames = null): array
    {
        $dateTimeFormat = (string) config('app.datetime_format', 'd-m-Y h:i A');
        $methodNames ??= PayoutController::methodNames();
        $rep = $item->salesRep;

        return [
            'id' => $item->id,
            'rep_id' => $item->sales_representative_id,
            'rep_name' => (string) ($rep?->name ?? '--'),
            'rep_email' => (string) ($rep?->email ?? ''),
            'rep_url' => $rep ? route('admin.sales-reps.show', ['sales_rep' => $rep->id, 'tab' => 'payouts']) : null,
            // Where the rep asked to be paid, and the method to pre-select.
            'pay_to' => $rep?->payoutAccountSummary($methodNames),
            'pay_to_method' => $rep?->payout_method_default,
            'amount' => (float) $item->amount,
            'paid_amount' => $item->paid_amount !== null ? (float) $item->paid_amount : null,
            'currency' => $item->currency,
            'status' => $item->status,
            'note' => $item->note,
            'admin_note' => $item->admin_note,
            'method' => $item->payout_method ? ($methodNames[$item->payout_method] ?? $item->payout_method) : null,
            'reference' => $item->reference,
            'processed_by' => $item->processor?->name,
            'requested_at' => $item->created_at?->format($dateTimeFormat),
            'processed_at' => $item->processed_at?->format($dateTimeFormat),
            // What can actually be paid now, in case it changed since the request.
            'payable_now' => $item->isPending() && $availability !== null
                ? max(0, min((float) $item->amount, (float) $availability['available']))
                : null,
            'balance' => $availability !== null ? (float) $availability['balance'] : null,
            'routes' => $item->isPending() && $rep ? [
                'approve' => route('admin.sales-reps.payout-requests.approve', [$rep, $item]),
                'reject' => route('admin.sales-reps.payout-requests.reject', [$rep, $item]),
            ] : null,
        ];
    }
}
