<?php

namespace App\Services;

use App\Models\AccountingEntry;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\StatusAuditLog;
use App\Models\Subscription;
use App\Support\SystemLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Moves a subscription onto another plan, including between billing
 * intervals (monthly ⇄ yearly), so the billing window, the next invoice date
 * and any money already paid all line up with the new plan.
 *
 * The subscription window (current_period_start..current_period_end) always
 * describes the next period that has NOT been invoiced yet, because
 * BillingService rolls it forward as soon as it raises an invoice. That makes
 * current_period_start the first unbilled day, which both timings build on:
 *
 *  - next_renewal: everything already invoiced stands. The new plan's first
 *    term starts on the first unbilled day. No money changes hands now.
 *  - now: the new term starts today and is invoiced straight away. Unused
 *    days on paid invoices become a credit on that invoice; unpaid invoices
 *    for periods that have not started are cancelled.
 */
class SubscriptionPlanChangeService
{
    public const TIMING_NEXT_RENEWAL = 'next_renewal';

    public const TIMING_NOW = 'now';

    public const TIMINGS = [self::TIMING_NEXT_RENEWAL, self::TIMING_NOW];

    public function __construct(
        private BillingService $billing,
        private InvoiceVatService $vat,
        private InvoicePaymentCompletionService $completion,
    ) {
    }

    /**
     * Work out what a plan change would do, without changing anything.
     *
     * @return array{
     *     timing: string,
     *     interval_changes: bool,
     *     old_interval: string,
     *     new_interval: string,
     *     new_amount: float,
     *     reshapes_window: bool,
     *     term_start: string|null,
     *     term_end: string|null,
     *     next_invoice_at: string|null,
     *     first_invoice_subtotal: float|null,
     *     first_invoice_total: float|null,
     *     credit: float,
     *     credit_lines: array<int, array{invoice_id: int, number: string, from: string, to: string, amount: float}>,
     *     cancel_invoice_ids: array<int, int>,
     *     blocked_reason: string|null,
     *     notes: array<int, string>
     * }
     */
    public function preview(Subscription $subscription, Plan $newPlan, float $newAmount, string $timing, ?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $subscription->loadMissing('plan');

        $oldInterval = (string) ($subscription->plan?->interval ?? 'monthly');
        $newInterval = (string) $newPlan->interval;
        $intervalChanges = $oldInterval !== $newInterval;

        $result = [
            'timing' => $timing,
            'interval_changes' => $intervalChanges,
            'old_interval' => $oldInterval,
            'new_interval' => $newInterval,
            'new_amount' => round($newAmount, 2),
            'reshapes_window' => false,
            'term_start' => null,
            'term_end' => null,
            'next_invoice_at' => null,
            'first_invoice_subtotal' => null,
            'first_invoice_total' => null,
            'credit' => 0.0,
            'credit_lines' => [],
            'cancel_invoice_ids' => [],
            'blocked_reason' => null,
            'notes' => [],
        ];

        if (! in_array($timing, self::TIMINGS, true)) {
            $result['blocked_reason'] = 'Choose when the change takes effect.';

            return $result;
        }

        if ((string) $subscription->status === 'cancelled') {
            $result['blocked_reason'] = 'A cancelled subscription cannot change plan.';

            return $result;
        }

        if ($timing === self::TIMING_NEXT_RENEWAL) {
            return $this->previewNextRenewal($subscription, $result, $newInterval, $newAmount, $today);
        }

        return $this->previewNow($subscription, $result, $newInterval, $newAmount, $today);
    }

    /**
     * Apply a plan change. Everything happens in one transaction; anything the
     * preview refuses throws without touching the subscription.
     *
     * @return array the preview that was applied, plus `invoice_id` for "now"
     */
    public function apply(
        Subscription $subscription,
        Plan $newPlan,
        float $newAmount,
        ?float $commissionAmount,
        string $timing,
        ?int $actorId = null,
        ?Carbon $today = null
    ): array {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $preview = $this->preview($subscription, $newPlan, $newAmount, $timing, $today);

        if ($preview['blocked_reason'] !== null) {
            throw new RuntimeException($preview['blocked_reason']);
        }

        $oldPlan = $subscription->plan;
        $oldAmount = (float) ($subscription->subscription_amount ?? $oldPlan?->price ?? 0);

        $result = DB::transaction(function () use ($subscription, $newPlan, $newAmount, $commissionAmount, $preview, $today, $actorId) {
            $subscription->update([
                'plan_id' => $newPlan->id,
                'subscription_amount' => $newAmount,
                'sales_rep_commission_amount' => $commissionAmount,
            ]);
            $subscription->setRelation('plan', $newPlan);

            $licensesRepointed = $newPlan->product_id
                ? $subscription->licenses()
                    ->where('product_id', '!=', $newPlan->product_id)
                    ->update(['product_id' => $newPlan->product_id])
                : 0;

            $applied = $preview + ['licenses_repointed' => $licensesRepointed, 'invoice_id' => null];

            if ($preview['reshapes_window']) {
                $subscription->update([
                    'current_period_start' => $preview['term_start'],
                    'current_period_end' => $preview['term_end'],
                    'next_invoice_at' => $preview['timing'] === self::TIMING_NOW
                        ? $today->toDateString()
                        : $preview['next_invoice_at'],
                ]);
            }

            if ($preview['timing'] === self::TIMING_NOW) {
                $applied['invoice_id'] = $this->billNow($subscription, $preview, $today, $actorId);
            }

            return $applied;
        });

        StatusAuditLog::logChange(
            Subscription::class,
            $subscription->id,
            (string) ($oldPlan?->name ?? 'unknown'),
            (string) $newPlan->name,
            'plan_change',
            $actorId,
            [
                'old_plan_id' => $oldPlan?->id,
                'new_plan_id' => $newPlan->id,
                'old_amount' => $oldAmount,
                'new_amount' => $newAmount,
                'timing' => $timing,
                'term_start' => $result['term_start'],
                'term_end' => $result['term_end'],
                'credit' => $result['credit'],
                'cancelled_invoice_ids' => $result['cancel_invoice_ids'],
                'invoice_id' => $result['invoice_id'],
                'licenses_repointed' => $result['licenses_repointed'],
            ]
        );

        return $result;
    }

    /**
     * Re-fit the unbilled window to the subscription's own plan, from the
     * first unbilled day. For a plan that was switched without moving the
     * window (e.g. monthly to yearly through the edit form). Bills nothing.
     */
    public function realignWindow(Subscription $subscription, ?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $subscription->loadMissing('plan');
        $interval = (string) $subscription->plan->interval;

        [$start, $end] = $this->billing->termWindow($interval, Carbon::parse($subscription->current_period_start));
        $nextInvoiceAt = $this->billing->nextInvoiceAt($start, $end, $today, $interval);

        $subscription->update([
            'current_period_start' => $start->toDateString(),
            'current_period_end' => $end->toDateString(),
            'next_invoice_at' => $nextInvoiceAt->toDateString(),
        ]);

        return [
            'term_start' => $start->toDateString(),
            'term_end' => $end->toDateString(),
            'next_invoice_at' => $nextInvoiceAt->toDateString(),
        ];
    }

    private function previewNextRenewal(Subscription $subscription, array $result, string $newInterval, float $newAmount, Carbon $today): array
    {
        $firstUnbilled = Carbon::parse($subscription->current_period_start)->startOfDay();

        if (! $result['interval_changes']) {
            // Same billing cycle: the window already has the right shape, and
            // the next invoice simply uses the new amount.
            $result['term_start'] = $firstUnbilled->toDateString();
            $result['term_end'] = Carbon::parse($subscription->current_period_end)->toDateString();
            $result['next_invoice_at'] = Carbon::parse($subscription->next_invoice_at)->toDateString();
            $result['first_invoice_subtotal'] = $this->billing->periodSubtotal(
                $newInterval,
                $newAmount,
                $firstUnbilled,
                Carbon::parse($subscription->current_period_end)
            );
            $result['first_invoice_total'] = $this->totalWithTax($result['first_invoice_subtotal'], $today);
            $result['notes'][] = 'The next invoice uses the new amount. Nothing already invoiced changes.';

            return $result;
        }

        [$start, $end] = $this->billing->termWindow($newInterval, $firstUnbilled);
        $nextInvoiceAt = $this->billing->nextInvoiceAt($start, $end, $today, $newInterval);

        $result['reshapes_window'] = true;
        $result['term_start'] = $start->toDateString();
        $result['term_end'] = $end->toDateString();
        $result['next_invoice_at'] = $nextInvoiceAt->toDateString();
        $result['first_invoice_subtotal'] = $this->billing->periodSubtotal($newInterval, $newAmount, $start, $end);
        $result['first_invoice_total'] = $this->totalWithTax($result['first_invoice_subtotal'], $today);
        $result['notes'][] = sprintf(
            'Everything invoiced up to %s stays as it is. The %s term starts %s.',
            $start->copy()->subDay()->toDateString(),
            $newInterval,
            $start->toDateString()
        );

        if ($nextInvoiceAt->lessThanOrEqualTo($today)) {
            $result['notes'][] = 'That term has already started, so the next billing run invoices it.';
        }

        return $result;
    }

    private function previewNow(Subscription $subscription, array $result, string $newInterval, float $newAmount, Carbon $today): array
    {
        if (! in_array((string) $subscription->status, ['active', 'suspended'], true)) {
            $result['blocked_reason'] = 'Only an active or suspended subscription can be billed now. Switch at the next renewal instead.';

            return $result;
        }

        $invoices = Invoice::query()
            ->where('subscription_id', $subscription->id)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->get();

        $credit = 0.0;

        foreach ($invoices as $invoice) {
            if (! $invoice->period_start || ! $invoice->period_end) {
                // A legacy or manual invoice has no period to prorate. Only a
                // problem if it might still be running, i.e. it is recent.
                if ($invoice->issue_date && $invoice->issue_date->greaterThanOrEqualTo($today->copy()->subYear())
                    && in_array((string) $invoice->status, ['unpaid', 'overdue'], true)) {
                    $result['blocked_reason'] = sprintf(
                        'Invoice #%s has no billing period, so its unused time cannot be worked out. Switch at the next renewal instead.',
                        $invoice->number ?: $invoice->id
                    );

                    return $result;
                }

                continue;
            }

            $periodStart = $invoice->period_start->copy()->startOfDay();
            $periodEnd = $invoice->period_end->copy()->startOfDay();

            if ($periodEnd->lessThan($today)) {
                continue; // fully used
            }

            $settled = $this->settledAmount($invoice);
            $isOpen = in_array((string) $invoice->status, ['unpaid', 'overdue'], true);

            if ($isOpen && $periodStart->greaterThanOrEqualTo($today) && $settled <= 0.009) {
                // Billed ahead for a period that has not started: withdraw it.
                $result['cancel_invoice_ids'][] = (int) $invoice->id;
                continue;
            }

            if ($isOpen) {
                $result['blocked_reason'] = sprintf(
                    'Invoice #%s for %s to %s is not fully paid. Settle it first, or switch at the next renewal.',
                    $invoice->number ?: $invoice->id,
                    $periodStart->toDateString(),
                    $periodEnd->toDateString()
                );

                return $result;
            }

            $unusedFrom = $periodStart->greaterThan($today) ? $periodStart : $today;
            $totalDays = $periodStart->diffInDays($periodEnd) + 1;
            $unusedDays = $unusedFrom->diffInDays($periodEnd) + 1;
            $amount = round($settled * min(1, $unusedDays / max(1, $totalDays)), 2);

            if ($amount <= 0) {
                continue;
            }

            $credit += $amount;
            $result['credit_lines'][] = [
                'invoice_id' => (int) $invoice->id,
                'number' => (string) ($invoice->number ?: $invoice->id),
                'from' => $unusedFrom->toDateString(),
                'to' => $periodEnd->toDateString(),
                'amount' => $amount,
            ];
        }

        [$start, $end] = $this->billing->termWindow($newInterval, $today);
        $subtotal = $this->billing->periodSubtotal($newInterval, $newAmount, $start, $end);
        $total = $this->totalWithTax($subtotal, $today);

        $result['reshapes_window'] = true;
        $result['term_start'] = $start->toDateString();
        $result['term_end'] = $end->toDateString();
        $result['first_invoice_subtotal'] = $subtotal;
        $result['first_invoice_total'] = $total;
        $result['credit'] = round($credit, 2);

        [$nextStart, $nextEnd] = $this->billing->termWindow($newInterval, $newInterval === 'monthly' ? $end->copy()->addDay() : $end);
        $result['next_invoice_at'] = $this->billing->nextInvoiceAt($nextStart, $nextEnd, $today, $newInterval)->toDateString();

        if ($result['credit'] > $total + 0.009) {
            $result['blocked_reason'] = sprintf(
                'The unused time already paid for (%s) is more than the first %s invoice (%s). Switch at the next renewal instead.',
                number_format($result['credit'], 2),
                $newInterval,
                number_format($total, 2)
            );

            return $result;
        }

        $result['notes'][] = sprintf(
            'A %s invoice for %s to %s is raised today%s.',
            $newInterval,
            $start->toDateString(),
            $end->toDateString(),
            $result['credit'] > 0 ? ', less '.number_format($result['credit'], 2).' credit for unused paid time' : ''
        );

        if ($result['cancel_invoice_ids'] !== []) {
            $result['notes'][] = 'Unpaid invoices for periods that have not started are cancelled.';
        }

        return $result;
    }

    /**
     * Raise the first invoice of an immediate change and settle what was
     * already paid against it. Returns the new invoice id.
     */
    private function billNow(Subscription $subscription, array $preview, Carbon $today, ?int $actorId): int
    {
        foreach ($preview['cancel_invoice_ids'] as $invoiceId) {
            $invoice = Invoice::find($invoiceId);
            $previous = (string) $invoice->status;
            $invoice->update(['status' => 'cancelled']);
            StatusAuditLog::logChange(Invoice::class, $invoice->id, $previous, 'cancelled', 'plan_change', $actorId);
        }

        $invoice = $this->billing->generateInvoiceForSubscription($subscription->fresh(['plan', 'customer']), $today);

        if (! $invoice) {
            // generateInvoiceForSubscription() declines when it finds an
            // invoice already issued inside the new window.
            throw new RuntimeException('An invoice was already issued for this subscription today. Switch at the next renewal instead.');
        }

        if ($preview['credit'] > 0) {
            $credit = min($preview['credit'], (float) $invoice->total);

            AccountingEntry::create([
                'entry_date' => $today->toDateString(),
                'type' => 'credit',
                'amount' => $credit,
                'currency' => (string) $invoice->currency,
                'description' => 'Unused time on the previous plan: '.collect($preview['credit_lines'])
                    ->map(fn ($line) => sprintf('#%s %s to %s', $line['number'], $line['from'], $line['to']))
                    ->implode(', '),
                'reference' => 'plan-change-'.$subscription->id,
                'customer_id' => $invoice->customer_id,
                'invoice_id' => $invoice->id,
                'created_by' => $actorId,
            ]);

            if ($credit >= (float) $invoice->total - 0.009) {
                $this->completion->complete($invoice, [
                    'reason' => 'plan_change_credit',
                    'actor_id' => $actorId,
                    'notify' => false,
                ]);
            }
        }

        SystemLogger::write('activity', 'Plan changed with immediate billing.', [
            'subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'credit' => $preview['credit'],
            'cancelled_invoice_ids' => $preview['cancel_invoice_ids'],
        ], $actorId);

        return (int) $invoice->id;
    }

    /**
     * What the customer has actually settled on an invoice. One marked paid
     * by hand without a payment entry counts as paid in full.
     */
    private function settledAmount(Invoice $invoice): float
    {
        $entries = (float) AccountingEntry::query()
            ->where('invoice_id', $invoice->id)
            ->whereIn('type', ['payment', 'credit'])
            ->sum('amount');

        if ((string) $invoice->status === 'paid' && $entries <= 0.009) {
            return (float) $invoice->total;
        }

        return min((float) $invoice->total, $entries);
    }

    private function totalWithTax(float $subtotal, Carbon $issueDate): float
    {
        return round((float) ($this->vat->calculateTotals($subtotal, 0.0, $issueDate)['total'] ?? $subtotal), 2);
    }
}
