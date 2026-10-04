<?php

namespace App\Services;

use App\Models\AccountingEntry;
use App\Models\CommissionAuditLog;
use App\Models\CommissionEarning;
use App\Models\CommissionPayout;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Subscription;
use Illuminate\Support\Collection;

/**
 * One sales rep's commission account, the way the business reads it:
 *
 *  - commission set for the rep (total) versus the part clients have actually
 *    paid for (earned). A project's commission is earned in proportion to
 *    what its client has paid; an invoice's commission once it is paid.
 *  - everything that went to the rep: regular payouts, company advances, and
 *    client payments the rep collected and kept (recorded as "advance"
 *    payouts with an invoice source), less money the rep paid back.
 *  - the balance between the two, overall and per project / service.
 *
 * Every page that shows a rep's money reads it from here, so they agree.
 */
class SalesRepStatementService
{
    public const ACTIVE_EARNING_STATUSES = ['pending', 'earned', 'payable', 'paid'];

    /** @var array<int, string> invoice id => statement line key */
    private array $invoiceLineKeys = [];

    /**
     * @return array<string, mixed>
     */
    public function forRep(int $repId): array
    {
        $earnings = $this->earnings($repId);
        $ratios = $this->realization($earnings);
        $payouts = $this->paidPayouts($repId);

        $lines = [];
        $line = function (string $key) use (&$lines): array {
            return $lines[$key] ??= [
                'key' => $key,
                'label' => '--',
                'customer' => null,
                'kind' => explode(':', $key)[0],
                'commission_total' => 0.0,
                'commission_earned' => 0.0,
                'client_total' => 0.0,
                'client_paid' => 0.0,
                'taken' => 0.0,
            ];
        };

        foreach ($earnings as $earning) {
            $key = $this->earningLineKey($earning);
            $entry = $line($key);
            $commission = (float) $earning->commission_amount;
            $entry['commission_total'] += $commission;
            $entry['commission_earned'] += round($commission * ($ratios[$earning->id] ?? 0), 2);
            $lines[$key] = $entry;
        }

        $taken = ['regular' => 0.0, 'advance' => 0.0, 'retained' => 0.0];
        $recovered = 0.0;

        foreach ($payouts as $payout) {
            $amount = (float) $payout->total_amount;
            $kind = $this->payoutKind($payout);

            if ($kind === 'recovery') {
                $recovered += abs($amount);
                continue;
            }

            $taken[$kind] += $amount;

            foreach ($this->payoutAllocation($payout, $earnings) as $key => $share) {
                $entry = $line($key);
                $entry['taken'] += $share;
                $lines[$key] = $entry;
            }
        }

        $lines = $this->describeLines($lines);

        $commissionTotal = round($earnings->sum(fn ($e) => (float) $e->commission_amount), 2);
        $commissionEarned = round(collect($lines)->sum('commission_earned'), 2);
        $grossTaken = round(array_sum($taken), 2);
        $recovered = round($recovered, 2);
        $netTaken = round($grossTaken - $recovered, 2);

        return [
            'commission_total' => $commissionTotal,
            'commission_earned' => $commissionEarned,
            'waiting_on_clients' => round(max(0, $commissionTotal - $commissionEarned), 2),
            'earned_percent' => $commissionTotal > 0 ? round($commissionEarned / $commissionTotal * 100, 1) : 0.0,
            'taken' => [
                'payouts' => round($taken['regular'], 2),
                'advances' => round($taken['advance'], 2),
                'retained' => round($taken['retained'], 2),
                'gross' => $grossTaken,
                'recovered' => $recovered,
                'net' => $netTaken,
            ],
            // Negative: the rep holds that much more than clients have paid for.
            'balance' => round($commissionEarned - $netTaken, 2),
            'balance_if_all_paid' => round($commissionTotal - $netTaken, 2),
            'lines' => array_values($lines),
        ];
    }

    /**
     * Share of each earning's commission that its client has paid for, 0..1.
     *
     * @param  Collection<int, CommissionEarning>  $earnings
     * @return array<int, float>
     */
    public function realization(Collection $earnings): array
    {
        $projectIds = $earnings->where('source_type', 'project')->pluck('project_id')->filter()->unique();
        $projectRatios = [];

        if ($projectIds->isNotEmpty()) {
            $budgets = Project::withTrashed()->whereIn('id', $projectIds)->pluck('total_budget', 'id');
            $projectInvoices = Invoice::query()->whereIn('project_id', $projectIds)->get(['id', 'project_id', 'status', 'total']);
            $settled = $this->settledAmounts($projectInvoices);

            foreach ($projectIds as $projectId) {
                $paid = $projectInvoices->where('project_id', $projectId)->sum(fn ($invoice) => $settled[$invoice->id] ?? 0);
                $budget = (float) ($budgets[$projectId] ?? 0);
                $projectRatios[$projectId] = $budget > 0 ? min(1, $paid / $budget) : ($paid > 0 ? 1.0 : 0.0);
            }
        }

        $invoiceIds = $earnings->where('source_type', '!=', 'project')->pluck('invoice_id')->filter()->unique();
        $invoices = Invoice::query()->whereIn('id', $invoiceIds)->get(['id', 'status', 'total'])->keyBy('id');
        $invoiceSettled = $this->settledAmounts($invoices->values());

        $ratios = [];
        foreach ($earnings as $earning) {
            if ($earning->source_type === 'project') {
                $ratios[$earning->id] = $projectRatios[$earning->project_id] ?? 0.0;
            } elseif ($earning->invoice_id && $invoices->has($earning->invoice_id)) {
                $total = (float) $invoices[$earning->invoice_id]->total;
                $paid = $invoiceSettled[$earning->invoice_id] ?? 0;
                $ratios[$earning->id] = $total > 0 ? min(1, $paid / $total) : ($invoices[$earning->invoice_id]->status === 'paid' ? 1.0 : 0.0);
            } else {
                $ratios[$earning->id] = 0.0;
            }
        }

        return $ratios;
    }

    /**
     * What the client has paid on each invoice. An invoice marked paid counts
     * in full even without a recorded payment; a refunded one counts nothing.
     * Credits are write-offs, not client money, so they are left out.
     *
     * @param  Collection<int, Invoice>  $invoices
     * @return array<int, float>
     */
    private function settledAmounts(Collection $invoices): array
    {
        if ($invoices->isEmpty()) {
            return [];
        }

        $payments = AccountingEntry::query()
            ->whereIn('invoice_id', $invoices->pluck('id'))
            ->where('type', 'payment')
            ->selectRaw('invoice_id, SUM(amount) as paid')
            ->groupBy('invoice_id')
            ->pluck('paid', 'invoice_id');

        $settled = [];
        foreach ($invoices as $invoice) {
            $total = (float) $invoice->total;
            $settled[$invoice->id] = match ((string) $invoice->status) {
                'paid' => $total,
                'refunded' => 0.0,
                default => min($total, (float) ($payments[$invoice->id] ?? 0)),
            };
        }

        return $settled;
    }

    /**
     * @return Collection<int, CommissionEarning>
     */
    private function earnings(int $repId): Collection
    {
        $earnings = app(CommissionService::class)->dedupedEarningsQuery($repId)
            ->whereIn('ce.status', self::ACTIVE_EARNING_STATUSES)
            ->select('ce.*')
            ->get();

        $this->rememberInvoiceLineKeys($earnings->pluck('invoice_id')->filter()->unique()->all());

        return $earnings;
    }

    /**
     * @return Collection<int, CommissionPayout>
     */
    private function paidPayouts(int $repId): Collection
    {
        $payouts = CommissionPayout::query()
            ->where('sales_representative_id', $repId)
            ->where('status', 'paid')
            ->orderBy('id')
            ->get();

        $metadata = CommissionAuditLog::query()
            ->whereIn('commission_payout_id', $payouts->pluck('id'))
            ->where('action', 'advance_payment')
            ->get(['commission_payout_id', 'metadata'])
            ->keyBy('commission_payout_id');

        return $payouts->each(function (CommissionPayout $payout) use ($metadata) {
            $meta = $metadata->get($payout->id)?->metadata;
            $payout->setAttribute('source_meta', is_array($meta) ? $meta : (json_decode((string) $meta, true) ?: []));
        });
    }

    private function payoutKind(CommissionPayout $payout): string
    {
        return match ((string) ($payout->type ?? 'regular')) {
            'recovery' => 'recovery',
            'advance' => (($payout->source_meta['source_type'] ?? null) === 'invoice') ? 'retained' : 'advance',
            default => 'regular',
        };
    }

    /**
     * Which project / service a payout was taken against.
     *
     * @return array<string, float>
     */
    private function payoutAllocation(CommissionPayout $payout, Collection $earnings): array
    {
        $amount = (float) $payout->total_amount;

        if ($this->payoutKind($payout) === 'regular') {
            $linked = $earnings->where('commission_payout_id', $payout->id);
            $linkedTotal = (float) $linked->sum('commission_amount');

            if ($linkedTotal > 0) {
                $shares = [];
                foreach ($linked as $earning) {
                    $key = $this->earningLineKey($earning);
                    $shares[$key] = ($shares[$key] ?? 0) + round($amount * (float) $earning->commission_amount / $linkedTotal, 2);
                }

                return $shares;
            }
        }

        $meta = $payout->source_meta ?? [];
        $sourceType = $meta['source_type'] ?? null;
        $sourceId = (int) ($meta['source_id'] ?? 0);

        if ($sourceType === 'project' && $sourceId) {
            return ['project:'.$sourceId => $amount];
        }

        if ($sourceType === 'subscription' && $sourceId) {
            return ['subscription:'.$sourceId => $amount];
        }

        if ($sourceType === 'invoice' && $sourceId) {
            return [$this->invoiceLineKeyById($sourceId) => $amount];
        }

        if ($payout->project_id) {
            return ['project:'.$payout->project_id => $amount];
        }

        return ['other:0' => $amount];
    }

    private function earningLineKey(CommissionEarning $earning): string
    {
        if ($earning->source_type === 'project' && $earning->project_id) {
            return 'project:'.$earning->project_id;
        }

        if ($earning->subscription_id) {
            return 'subscription:'.$earning->subscription_id;
        }

        if ($earning->invoice_id) {
            return $this->invoiceLineKeyById((int) $earning->invoice_id);
        }

        return 'other:0';
    }

    /**
     * @param  array<int, int>  $invoiceIds
     */
    private function rememberInvoiceLineKeys(array $invoiceIds): void
    {
        $missing = array_diff($invoiceIds, array_keys($this->invoiceLineKeys));

        if ($missing === []) {
            return;
        }

        Invoice::with('maintenance:id,project_id')
            ->whereIn('id', $missing)
            ->get(['id', 'project_id', 'subscription_id', 'maintenance_id'])
            ->each(function (Invoice $invoice) {
                $this->invoiceLineKeys[$invoice->id] = $this->invoiceLineKey($invoice);
            });
    }

    private function invoiceLineKeyById(int $invoiceId): string
    {
        $this->rememberInvoiceLineKeys([$invoiceId]);

        return $this->invoiceLineKeys[$invoiceId] ?? 'invoice:'.$invoiceId;
    }

    private function invoiceLineKey(Invoice $invoice): string
    {
        if ($invoice->project_id) {
            return 'project:'.$invoice->project_id;
        }

        if ($invoice->subscription_id) {
            return 'subscription:'.$invoice->subscription_id;
        }

        if ($invoice->maintenance?->project_id) {
            return 'project:'.$invoice->maintenance->project_id;
        }

        return 'invoice:'.$invoice->id;
    }

    /**
     * Names, client totals and balances for each line, largest gap first.
     *
     * @param  array<string, array<string, mixed>>  $lines
     * @return array<string, array<string, mixed>>
     */
    private function describeLines(array $lines): array
    {
        $ids = fn (string $kind) => collect(array_keys($lines))
            ->filter(fn ($key) => str_starts_with($key, $kind.':'))
            ->map(fn ($key) => (int) explode(':', $key)[1])
            ->values();

        $projects = Project::withTrashed()->with('customer:id,name')->whereIn('id', $ids('project'))->get()->keyBy('id');
        $projectInvoices = Invoice::query()->whereIn('project_id', $projects->keys())->get(['id', 'project_id', 'status', 'total']);
        $projectSettled = $this->settledAmounts($projectInvoices);

        $subscriptions = Subscription::with(['customer:id,name', 'plan:id,name,product_id', 'plan.product:id,name'])
            ->whereIn('id', $ids('subscription'))->get()->keyBy('id');
        $subscriptionInvoices = Invoice::query()->whereIn('subscription_id', $subscriptions->keys())->get(['id', 'subscription_id', 'status', 'total']);
        $subscriptionSettled = $this->settledAmounts($subscriptionInvoices);

        $invoices = Invoice::with('customer:id,name')->whereIn('id', $ids('invoice'))->get()->keyBy('id');
        $invoiceSettled = $this->settledAmounts($invoices->values());

        foreach ($lines as $key => $entry) {
            [$kind, $id] = explode(':', $key);
            $id = (int) $id;

            if ($kind === 'project' && ($project = $projects->get($id))) {
                $entry['label'] = (string) $project->name;
                $entry['customer'] = $project->customer?->name;
                $entry['client_total'] = (float) $project->total_budget;
                $entry['client_paid'] = (float) $projectInvoices->where('project_id', $id)->sum(fn ($i) => $projectSettled[$i->id] ?? 0);
            } elseif ($kind === 'subscription' && ($subscription = $subscriptions->get($id))) {
                $entry['label'] = trim(($subscription->plan?->product?->name ?? 'Service').' › '.($subscription->plan?->name ?? '--'));
                $entry['customer'] = $subscription->customer?->name;
                $billed = $subscriptionInvoices->where('subscription_id', $id)->whereNotIn('status', ['cancelled', 'refunded']);
                $entry['client_total'] = (float) $billed->sum('total');
                $entry['client_paid'] = (float) $billed->sum(fn ($i) => $subscriptionSettled[$i->id] ?? 0);
            } elseif ($kind === 'invoice' && ($invoice = $invoices->get($id))) {
                $entry['label'] = 'Invoice #'.($invoice->number ?: $invoice->id);
                $entry['customer'] = $invoice->customer?->name;
                $entry['client_total'] = (float) $invoice->total;
                $entry['client_paid'] = (float) ($invoiceSettled[$id] ?? 0);
            } elseif ($kind === 'other') {
                $entry['label'] = 'Not linked to a project or service';
            }

            foreach (['commission_total', 'commission_earned', 'client_total', 'client_paid', 'taken'] as $field) {
                $entry[$field] = round((float) $entry[$field], 2);
            }

            $entry['client_paid_percent'] = $entry['client_total'] > 0
                ? round(min(100, $entry['client_paid'] / $entry['client_total'] * 100), 1)
                : null;
            $entry['balance'] = round($entry['commission_earned'] - $entry['taken'], 2);
            $lines[$key] = $entry;
        }

        uasort($lines, fn ($a, $b) => $a['balance'] <=> $b['balance']);

        return $lines;
    }
}
