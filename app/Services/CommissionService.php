<?php

namespace App\Services;

use App\Models\CommissionAuditLog;
use App\Models\CommissionEarning;
use App\Models\CommissionRule;
use App\Models\CommissionPayout;
use App\Models\Invoice;
use App\Models\PaymentAttempt;
use App\Models\Project;
use App\Models\SalesRepresentative;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CommissionService
{
    /**
    * Resolve the most specific active rule for a given source.
    */
    public function resolveCommissionRule(string $sourceType, object $sourceModel): ?CommissionRule
    {
        $rules = CommissionRule::query()
            ->where('active', true)
            ->where('source_type', $sourceType)
            ->get();

        $repId = $this->resolveSalesRepIdFromSource($sourceModel);
        $planId = $this->resolvePlanIdFromSource($sourceModel);
        $productId = $this->resolveProductIdFromSource($sourceModel);
        $projectType = method_exists($sourceModel, 'type') ? (string) $sourceModel->type : null;

        $priorities = [
            fn (CommissionRule $rule) => $rule->scope_type === 'sales_rep' && $repId && $rule->scope_id === (string) $repId,
            fn (CommissionRule $rule) => $rule->scope_type === 'plan' && $planId && $rule->scope_id === (string) $planId,
            fn (CommissionRule $rule) => $rule->scope_type === 'product' && $productId && $rule->scope_id === (string) $productId,
            fn (CommissionRule $rule) => $rule->scope_type === 'project_type' && $projectType && $rule->scope_id === $projectType,
            fn (CommissionRule $rule) => $rule->scope_id === null || $rule->scope_id === '',
        ];

        foreach ($priorities as $matcher) {
            $match = $rules->first($matcher);
            if ($match) {
                return $match;
            }
        }

        return null;
    }

    /**
    * Calculate commission amount for a given paid amount and rule.
    */
    public function calculateCommission(float $paidAmount, CommissionRule $rule): float
    {
        $base = $rule->commission_type === 'percentage'
            ? ($paidAmount * ($rule->value / 100))
            : $rule->value;

        if (! is_null($rule->cap_amount)) {
            $base = min($base, (float) $rule->cap_amount);
        }

        return round($base, 2);
    }

    /**
    * Create or update an earning when an invoice is paid (idempotent by idempotency_key).
    */
    public function createOrUpdateEarningOnInvoicePaid(Invoice $invoice): ?CommissionEarning
    {
        $salesRepId = $this->resolveSalesRepIdForInvoice($invoice);
        if (! $salesRepId) {
            return null;
        }

        $sourceType = $invoice->subscription_id ? 'maintenance' : 'plan';
        $rule = $this->resolveCommissionRule($sourceType, $invoice);
        $paidAmount = (float) $invoice->total;
        $commissionAmount = $rule ? $this->calculateCommission($paidAmount, $rule) : 0.0;
        $idempotencyKey = sprintf('invoice:%s:rep:%s:source:%s', $invoice->id, $salesRepId, $sourceType);

        return DB::transaction(function () use ($invoice, $salesRepId, $commissionAmount, $paidAmount, $sourceType, $idempotencyKey) {
            $earning = CommissionEarning::lockForUpdate()->where('idempotency_key', $idempotencyKey)->first();

            $payload = [
                'sales_representative_id' => $salesRepId,
                'source_type' => $sourceType,
                'source_id' => $invoice->id,
                'invoice_id' => $invoice->id,
                'subscription_id' => $invoice->subscription_id,
                'project_id' => null,
                'customer_id' => $invoice->customer_id,
                'currency' => $invoice->currency,
                'paid_amount' => $paidAmount,
                'commission_amount' => $commissionAmount,
                'status' => 'earned',
                'earned_at' => Carbon::now(),
                'idempotency_key' => $idempotencyKey,
            ];

            if (! $earning) {
                $earning = CommissionEarning::create($payload);
                $this->logStatusChange($earning, null, 'earned', 'invoice_paid');
            } else {
                $previousStatus = $earning->status;
                // If already paid or reversed, keep the status but update metadata/amounts.
                if (in_array($earning->status, ['paid', 'reversed'], true)) {
                    $payload['status'] = $earning->status;
                    $payload['earned_at'] = $earning->earned_at;
                    $payload['payable_at'] = $earning->payable_at;
                    $payload['paid_at'] = $earning->paid_at;
                    $payload['reversed_at'] = $earning->reversed_at;
                }

                $earning->update($payload);

                if ($previousStatus !== $earning->status) {
                    $this->logStatusChange($earning, $previousStatus, $earning->status, 'invoice_paid_update');
                }
            }

            return $earning;
        });
    }

    /**
    * Mark project-linked earnings as payable when a project is completed.
    */
    public function markEarningPayableOnProjectCompleted(Project $project): int
    {
        $now = Carbon::now();
        $count = 0;

        $earnings = CommissionEarning::query()
            ->where('source_type', 'project')
            ->where('source_id', $project->id)
            ->whereIn('status', ['pending', 'earned'])
            ->lockForUpdate()
            ->get();

        foreach ($earnings as $earning) {
            $previousStatus = $earning->status;
            $earning->update([
                'status' => 'payable',
                'payable_at' => $earning->payable_at ?? $now,
            ]);
            $this->logStatusChange($earning, $previousStatus, 'payable', 'project_completed');
            $count++;
        }

        return $count;
    }

    /**
    * Create or update commission earnings for sales reps assigned to a project.
    */
    public function syncProjectEarnings(Project $project, array $salesRepSync): void
    {
        $repAmounts = [];
        foreach ($salesRepSync as $repId => $payload) {
            $amount = (float) ($payload['amount'] ?? 0);
            if ($amount > 0) {
                $repAmounts[(int) $repId] = $amount;
            }
        }

        $existing = CommissionEarning::query()
            ->where('source_type', 'project')
            ->where('source_id', $project->id)
            ->get()
            ->keyBy('idempotency_key');

        $now = Carbon::now();
        $targetStatus = $project->status === 'complete' ? 'payable' : 'earned';

        foreach ($repAmounts as $repId => $amount) {
            $idempotencyKey = sprintf('project:%s:rep:%s', $project->id, $repId);
            $earning = $existing->get($idempotencyKey);

            $payload = [
                'sales_representative_id' => $repId,
                'source_type' => 'project',
                'source_id' => $project->id,
                'invoice_id' => null,
                'subscription_id' => $project->subscription_id,
                'project_id' => $project->id,
                'customer_id' => $project->customer_id,
                'currency' => $project->currency,
                'paid_amount' => (float) ($project->total_budget ?? $amount),
                'commission_amount' => $amount,
                'status' => $targetStatus,
                'earned_at' => $now,
                'payable_at' => $targetStatus === 'payable' ? $now : null,
                'idempotency_key' => $idempotencyKey,
            ];

            if (! $earning) {
                $earning = CommissionEarning::create($payload);
                $this->logStatusChange($earning, null, $targetStatus, 'project_assigned');
                continue;
            }

            if (in_array($earning->status, ['paid', 'reversed'], true)) {
                continue;
            }

            $previousStatus = $earning->status;
            $payload['earned_at'] = $earning->earned_at ?? $now;
            if ($earning->status === 'payable' && $targetStatus !== 'payable') {
                $payload['status'] = 'payable';
                $payload['payable_at'] = $earning->payable_at ?? $now;
            }

            $earning->update($payload);

            if ($previousStatus !== $earning->status) {
                $this->logStatusChange($earning, $previousStatus, $earning->status, 'project_assignment_update');
            }
        }

        if (! empty($repAmounts)) {
            $activeKeys = array_map(
                fn ($repId) => sprintf('project:%s:rep:%s', $project->id, $repId),
                array_keys($repAmounts)
            );
        } else {
            $activeKeys = [];
        }

        $removals = CommissionEarning::query()
            ->where('source_type', 'project')
            ->where('source_id', $project->id)
            ->when($activeKeys, fn ($query) => $query->whereNotIn('idempotency_key', $activeKeys))
            ->get();

        foreach ($removals as $earning) {
            if (in_array($earning->status, ['paid', 'reversed'], true)) {
                continue;
            }

            $previousStatus = $earning->status;
            $earning->update([
                'status' => 'reversed',
                'reversed_at' => $now,
            ]);
            $this->logStatusChange($earning, $previousStatus, 'reversed', 'project_assignment_removed');
        }
    }

    /**
    * Backfill missing project earnings for selected sales reps.
    */
    public function ensureProjectEarningsForRepIds(array $repIds): void
    {
        $repIds = array_values(array_filter(array_unique(array_map('intval', $repIds))));
        if (empty($repIds)) {
            return;
        }

        $assignments = DB::table('project_sales_representative')
            ->join('projects', 'project_sales_representative.project_id', '=', 'projects.id')
            ->whereIn('project_sales_representative.sales_representative_id', $repIds)
            ->where('project_sales_representative.amount', '>', 0)
            ->select([
                'project_sales_representative.project_id',
                'project_sales_representative.sales_representative_id',
                'project_sales_representative.amount',
                'projects.status',
                'projects.customer_id',
                'projects.subscription_id',
                'projects.currency',
                'projects.total_budget',
            ])
            ->get();

        if ($assignments->isEmpty()) {
            return;
        }

        $keys = $assignments->map(fn ($row) => sprintf('project:%s:rep:%s', $row->project_id, $row->sales_representative_id));
        $existingKeys = CommissionEarning::query()
            ->whereIn('idempotency_key', $keys)
            ->pluck('idempotency_key')
            ->all();

        $now = Carbon::now();

        foreach ($assignments as $row) {
            $key = sprintf('project:%s:rep:%s', $row->project_id, $row->sales_representative_id);
            if (in_array($key, $existingKeys, true)) {
                continue;
            }

            $status = $row->status === 'complete' ? 'payable' : 'earned';
            $earning = CommissionEarning::create([
                'sales_representative_id' => (int) $row->sales_representative_id,
                'source_type' => 'project',
                'source_id' => (int) $row->project_id,
                'invoice_id' => null,
                'subscription_id' => $row->subscription_id,
                'project_id' => (int) $row->project_id,
                'customer_id' => $row->customer_id,
                'currency' => $row->currency,
                'paid_amount' => (float) ($row->total_budget ?? $row->amount),
                'commission_amount' => (float) $row->amount,
                'status' => $status,
                'earned_at' => $now,
                'payable_at' => $status === 'payable' ? $now : null,
                'idempotency_key' => $key,
            ]);

            $this->logStatusChange($earning, null, $status, 'project_assignment_backfill');
        }
    }

    /**
    * Reverse earnings for a refunded/voided invoice or payment attempt.
    */
    public function reverseEarningsOnRefund(object $subject): int
    {
        $invoiceId = null;
        if ($subject instanceof Invoice) {
            $invoiceId = $subject->id;
        } elseif ($subject instanceof PaymentAttempt) {
            $invoiceId = $subject->invoice_id;
        }

        if (! $invoiceId) {
            return 0;
        }

        $now = Carbon::now();
        $earnings = CommissionEarning::query()
            ->where('invoice_id', $invoiceId)
            ->where('status', '!=', 'reversed')
            ->lockForUpdate()
            ->get();

        foreach ($earnings as $earning) {
            $previousStatus = $earning->status;
            $earning->update([
                'status' => 'reversed',
                'reversed_at' => $now,
                'commission_payout_id' => $earning->commission_payout_id, // keep linkage if already paid for audit
            ]);

            $this->logStatusChange($earning, $previousStatus, 'reversed', 'refund_or_chargeback');
        }

        return $earnings->count();
    }

    /**
    * Compute aggregate balances for a sales rep.
    */
    public function computeRepBalance(int $salesRepId): array
    {
        $baseQuery = CommissionEarning::query()->where('sales_representative_id', $salesRepId);

        $totalEarned = (float) $baseQuery
            ->whereIn('status', ['pending', 'earned', 'payable', 'paid'])
            ->sum('commission_amount');

        $paidEarnings = (float) CommissionEarning::query()
            ->where('sales_representative_id', $salesRepId)
            ->where('status', 'paid')
            ->sum('commission_amount');

        $payable = (float) CommissionEarning::query()
            ->where('sales_representative_id', $salesRepId)
            ->where('status', 'payable')
            ->sum('commission_amount');

        $advancePaid = 0.0;
        if ($this->commissionPayoutHasColumn('type')) {
            $advancePaid = (float) CommissionPayout::query()
                ->where('sales_representative_id', $salesRepId)
                ->where('type', 'advance')
                ->where('status', 'paid')
                ->sum('total_amount');
        }

        $totalPaid = $paidEarnings + $advancePaid;
        $overpaid = max(0, $totalPaid - $totalEarned);
        $netPayable = max(0, $payable - $overpaid);
        $outstanding = $totalEarned - $totalPaid;

        return [
            'total_earned' => $totalEarned,
            'total_paid' => $totalPaid,
            'payable_balance' => $netPayable,
            'payable_gross' => $payable,
            'advance_paid' => $advancePaid,
            'overpaid' => $overpaid,
            'outstanding' => $outstanding,
        ];
    }

    /**
    * Create a payout draft for selected payable earnings and lock them to the payout.
    */
    public function createPayout(int $salesRepId, array $earningIds, ?string $currency = 'BDT', ?string $payoutMethod = null, ?string $note = null): CommissionPayout
    {
        return DB::transaction(function () use ($salesRepId, $earningIds, $currency, $payoutMethod, $note) {
            $earnings = CommissionEarning::query()
                ->whereIn('id', $earningIds)
                ->where('sales_representative_id', $salesRepId)
                ->where('status', 'payable')
                ->whereNull('commission_payout_id')
                ->lockForUpdate()
                ->get();

            if ($earnings->isEmpty()) {
                throw new \RuntimeException('No payable earnings found for payout.');
            }

            $total = (float) $earnings->sum('commission_amount');

            $payload = [
                'sales_representative_id' => $salesRepId,
                'total_amount' => $total,
                'currency' => $currency ?? 'BDT',
                'payout_method' => $payoutMethod,
                'note' => $note,
                'status' => 'draft',
            ];

            if ($this->commissionPayoutHasColumn('type')) {
                $payload['type'] = 'regular';
            }

            $payout = CommissionPayout::create($payload);

            CommissionEarning::query()
                ->whereIn('id', $earnings->pluck('id'))
                ->update(['commission_payout_id' => $payout->id]);

            foreach ($earnings as $earning) {
                $this->logStatusChange($earning, $earning->status, $earning->status, 'payout_draft_created', [
                    'payout_id' => $payout->id,
                ]);
            }

            return $payout;
        });
    }

    private function commissionPayoutHasColumn(string $column): bool
    {
        static $cache = [];

        if (! array_key_exists($column, $cache)) {
            $cache[$column] = Schema::hasColumn('commission_payouts', $column);
        }

        return $cache[$column];
    }

    /**
    * Mark payout as paid and transition linked earnings to paid.
    */
    public function markPayoutPaid(CommissionPayout $payout, ?string $reference = null, ?string $note = null, ?string $payoutMethod = null): CommissionPayout
    {
        return DB::transaction(function () use ($payout, $reference, $note, $payoutMethod) {
            $payout->refresh();
            if ($payout->status === 'paid') {
                return $payout;
            }

            if ($payout->status === 'reversed') {
                throw new \RuntimeException('Cannot pay a reversed payout.');
            }

            $now = Carbon::now();

            $payout->update([
                'status' => 'paid',
                'paid_at' => $now,
                'reference' => $reference,
                'note' => $note ?? $payout->note,
                'payout_method' => $payoutMethod ?? $payout->payout_method,
            ]);

            $earnings = CommissionEarning::query()
                ->where('commission_payout_id', $payout->id)
                ->lockForUpdate()
                ->get();

            foreach ($earnings as $earning) {
                $previousStatus = $earning->status;
                $earning->update([
                    'status' => 'paid',
                    'paid_at' => $earning->paid_at ?? $now,
                ]);
                $this->logStatusChange($earning, $previousStatus, 'paid', 'payout_paid', [
                    'payout_id' => $payout->id,
                    'reference' => $reference,
                ]);
            }

            return $payout;
        });
    }

    /**
    * Reverse a payout and return earnings to payable state.
    */
    public function reversePayout(CommissionPayout $payout, ?string $note = null): CommissionPayout
    {
        return DB::transaction(function () use ($payout, $note) {
            $payout->refresh();
            if ($payout->status === 'reversed') {
                return $payout;
            }

            $now = Carbon::now();

            $earnings = CommissionEarning::query()
                ->where('commission_payout_id', $payout->id)
                ->lockForUpdate()
                ->get();

            foreach ($earnings as $earning) {
                $previousStatus = $earning->status;
                $earning->update([
                    'status' => 'payable',
                    'paid_at' => null,
                    'commission_payout_id' => null,
                ]);
                $this->logStatusChange($earning, $previousStatus, 'payable', 'payout_reversed', [
                    'payout_id' => $payout->id,
                ]);
            }

            $payout->update([
                'status' => 'reversed',
                'reversed_at' => $now,
                'note' => $note ?? $payout->note,
            ]);

            return $payout;
        });
    }

    private function resolveSalesRepIdFromSource(object $sourceModel): ?int
    {
        if (property_exists($sourceModel, 'sales_rep_id') && $sourceModel->sales_rep_id) {
            return (int) $sourceModel->sales_rep_id;
        }

        if ($sourceModel instanceof Invoice) {
            return $this->resolveSalesRepIdForInvoice($sourceModel);
        }

        if ($sourceModel instanceof Project) {
            return $this->resolveSalesRepIdForProject($sourceModel);
        }

        if ($sourceModel instanceof Subscription) {
            return $sourceModel->sales_rep_id ?: $sourceModel->customer?->default_sales_rep_id;
        }

        return null;
    }

    private function resolveSalesRepIdForInvoice(Invoice $invoice): ?int
    {
        $invoice->loadMissing(['subscription', 'customer', 'orders']);

        if ($invoice->subscription?->sales_rep_id) {
            return (int) $invoice->subscription->sales_rep_id;
        }

        $orderRep = $invoice->orders
            ? $invoice->orders->firstWhere('sales_rep_id', '!=', null)?->sales_rep_id
            : null;
        if ($orderRep) {
            return (int) $orderRep;
        }

        return $invoice->customer?->default_sales_rep_id
            ? (int) $invoice->customer->default_sales_rep_id
            : null;
    }

    private function resolveSalesRepIdForProject(Project $project): ?int
    {
        $project->loadMissing(['order', 'subscription', 'customer']);

        if ($project->order?->sales_rep_id) {
            return (int) $project->order->sales_rep_id;
        }

        if ($project->subscription?->sales_rep_id) {
            return (int) $project->subscription->sales_rep_id;
        }

        return $project->customer?->default_sales_rep_id
            ? (int) $project->customer->default_sales_rep_id
            : null;
    }

    private function resolvePlanIdFromSource(object $sourceModel): ?int
    {
        if ($sourceModel instanceof Subscription) {
            return $sourceModel->plan_id;
        }

        if ($sourceModel instanceof Invoice) {
            return $sourceModel->subscription?->plan_id;
        }

        if ($sourceModel instanceof Project) {
            return $sourceModel->subscription?->plan_id;
        }

        return null;
    }

    private function resolveProductIdFromSource(object $sourceModel): ?int
    {
        if ($sourceModel instanceof Subscription) {
            return $sourceModel->plan?->product_id;
        }

        if ($sourceModel instanceof Invoice) {
            return $sourceModel->subscription?->plan?->product_id;
        }

        if ($sourceModel instanceof Project) {
            return $sourceModel->subscription?->plan?->product_id;
        }

        return null;
    }

    private function logStatusChange(CommissionEarning $earning, ?string $from, ?string $to, string $action, ?array $metadata = null): void
    {
        CommissionAuditLog::create([
            'sales_representative_id' => $earning->sales_representative_id,
            'commission_earning_id' => $earning->id,
            'commission_payout_id' => $earning->commission_payout_id,
            'action' => $action,
            'status_from' => $from,
            'status_to' => $to,
            'metadata' => $metadata,
            'created_by' => auth()->id(),
        ]);
    }
}
