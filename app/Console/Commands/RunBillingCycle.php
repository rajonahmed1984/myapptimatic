<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunBillingCycle extends Command
{
    protected $signature = 'billing:run';

    protected $description = 'Generate invoices, mark overdue, apply late fees, and run automation.';

    public function handle(): int
    {
        $today = Carbon::today();

        $this->generateInvoices($today);
        $this->markOverdue($today);
        $this->applyLateFees($today);
        $this->applyAutoCancellation($today);
        $this->applySuspensions($today);
        $this->applyTerminations($today);
        $this->applyUnsuspensions();

        $this->info('Billing run completed.');

        return self::SUCCESS;
    }

    private function generateInvoices(Carbon $today): void
    {
        $subscriptions = Subscription::query()
            ->with('plan')
            ->where('status', 'active')
            ->whereDate('next_invoice_at', '<=', $today)
            ->get();

        foreach ($subscriptions as $subscription) {
            if ($subscription->cancel_at_period_end && $subscription->current_period_end->lessThanOrEqualTo($today)) {
                $subscription->update([
                    'status' => 'cancelled',
                    'auto_renew' => false,
                ]);
                continue;
            }

            if ($subscription->cancel_at_period_end && $subscription->current_period_end->greaterThan($today)) {
                $subscription->update([
                    'next_invoice_at' => $subscription->current_period_end->toDateString(),
                ]);
                continue;
            }

            $plan = $subscription->plan;
            $dueDays = (int) ($plan->invoice_due_days ?: Setting::getValue('invoice_due_days'));
            $invoiceGenerationDays = (int) Setting::getValue('invoice_generation_days');
            $currency = $plan->currency ?: Setting::getValue('currency');
            $issueDate = $today->copy();

            $invoice = Invoice::create([
                'customer_id' => $subscription->customer_id,
                'subscription_id' => $subscription->id,
                'number' => $this->nextInvoiceNumber($issueDate->year),
                'status' => 'unpaid',
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $issueDate->copy()->addDays($dueDays)->toDateString(),
                'subtotal' => $plan->price,
                'late_fee' => 0,
                'total' => $plan->price,
                'currency' => strtoupper($currency),
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => sprintf('%s (%s)', $plan->name, $plan->interval),
                'quantity' => 1,
                'unit_price' => $plan->price,
                'line_total' => $plan->price,
            ]);

            $periodStart = $subscription->current_period_end->copy();
            $periodEnd = $plan->interval === 'yearly'
                ? $periodStart->copy()->addYear()
                : $periodStart->copy()->addMonth();

            $nextInvoiceAt = $invoiceGenerationDays > 0
                ? $periodEnd->copy()->subDays($invoiceGenerationDays)
                : $periodEnd->copy();

            if ($nextInvoiceAt->lessThan($today)) {
                $nextInvoiceAt = $periodEnd->copy();
            }

            $subscription->update([
                'current_period_start' => $periodStart->toDateString(),
                'current_period_end' => $periodEnd->toDateString(),
                'next_invoice_at' => $nextInvoiceAt->toDateString(),
            ]);
        }
    }

    private function markOverdue(Carbon $today): void
    {
        $invoices = Invoice::query()
            ->where('status', 'unpaid')
            ->whereDate('due_date', '<', $today)
            ->get();

        foreach ($invoices as $invoice) {
            $invoice->update([
                'status' => 'overdue',
                'overdue_at' => $invoice->overdue_at ?? Carbon::now(),
            ]);
        }
    }

    private function applyLateFees(Carbon $today): void
    {
        $lateFeeDays = (int) Setting::getValue('late_fee_days');
        $lateFeeAmount = (float) Setting::getValue('late_fee_amount');
        $lateFeeType = Setting::getValue('late_fee_type');

        if ($lateFeeDays <= 0 || $lateFeeAmount <= 0) {
            return;
        }

        $targetDate = $today->copy()->subDays($lateFeeDays);

        $invoices = Invoice::query()
            ->whereIn('status', ['unpaid', 'overdue'])
            ->whereNull('late_fee_applied_at')
            ->whereDate('due_date', '<=', $targetDate)
            ->get();

        foreach ($invoices as $invoice) {
            $lateFee = $lateFeeType === 'percent'
                ? ($invoice->subtotal * ($lateFeeAmount / 100))
                : $lateFeeAmount;

            if ($lateFee <= 0) {
                continue;
            }

            $invoice->update([
                'late_fee' => $invoice->late_fee + $lateFee,
                'total' => $invoice->subtotal + $invoice->late_fee + $lateFee,
                'late_fee_applied_at' => Carbon::now(),
            ]);
        }
    }

    private function applyAutoCancellation(Carbon $today): void
    {
        if (! Setting::getValue('enable_auto_cancellation')) {
            return;
        }

        $days = (int) Setting::getValue('auto_cancellation_days');
        $targetDate = $today->copy()->subDays($days);

        $invoices = Invoice::query()
            ->whereIn('status', ['unpaid', 'overdue'])
            ->whereDate('due_date', '<=', $targetDate)
            ->get();

        foreach ($invoices as $invoice) {
            $invoice->update([
                'status' => 'cancelled',
            ]);
        }
    }

    private function applySuspensions(Carbon $today): void
    {
        if (! Setting::getValue('enable_suspension')) {
            return;
        }

        $suspendDays = (int) Setting::getValue('suspend_days');
        $targetDate = $today->copy()->subDays($suspendDays);

        $invoices = Invoice::query()
            ->with(['subscription.licenses', 'subscription.customer'])
            ->whereNotNull('subscription_id')
            ->whereIn('status', ['unpaid', 'overdue'])
            ->whereDate('due_date', '<=', $targetDate)
            ->get();

        foreach ($invoices as $invoice) {
            $subscription = $invoice->subscription;

            if (! $subscription || $subscription->status === 'cancelled') {
                continue;
            }

            if ($subscription->customer && $subscription->customer->access_override_until && $subscription->customer->access_override_until->isFuture()) {
                continue;
            }

            if ($subscription->status !== 'suspended') {
                $subscription->update(['status' => 'suspended']);
            }

            $subscription->licenses()
                ->where('status', 'active')
                ->update(['status' => 'suspended']);
        }
    }

    private function applyTerminations(Carbon $today): void
    {
        if (! Setting::getValue('enable_termination')) {
            return;
        }

        $terminationDays = (int) Setting::getValue('termination_days');
        $targetDate = $today->copy()->subDays($terminationDays);

        $invoices = Invoice::query()
            ->with(['subscription.licenses'])
            ->whereNotNull('subscription_id')
            ->whereIn('status', ['unpaid', 'overdue'])
            ->whereDate('due_date', '<=', $targetDate)
            ->get();

        foreach ($invoices as $invoice) {
            $subscription = $invoice->subscription;

            if (! $subscription || $subscription->status === 'cancelled') {
                continue;
            }

            $subscription->update([
                'status' => 'cancelled',
                'auto_renew' => false,
                'cancelled_at' => Carbon::now(),
            ]);

            $subscription->licenses()
                ->whereIn('status', ['active', 'suspended'])
                ->update(['status' => 'revoked']);
        }
    }

    private function applyUnsuspensions(): void
    {
        if (! Setting::getValue('enable_unsuspension')) {
            return;
        }

        $subscriptions = Subscription::query()
            ->with(['licenses'])
            ->where('status', 'suspended')
            ->get();

        foreach ($subscriptions as $subscription) {
            $latestInvoice = Invoice::query()
                ->where('subscription_id', $subscription->id)
                ->orderByDesc('due_date')
                ->first();

            if ($latestInvoice && $latestInvoice->status !== 'paid') {
                continue;
            }

            $openInvoices = Invoice::query()
                ->where('subscription_id', $subscription->id)
                ->whereIn('status', ['unpaid', 'overdue'])
                ->exists();

            if ($openInvoices) {
                continue;
            }

            $subscription->update(['status' => 'active']);
            $subscription->licenses()
                ->where('status', 'suspended')
                ->update(['status' => 'active']);
        }
    }

    private function nextInvoiceNumber(int $year): string
    {
        $sequence = Invoice::query()->whereYear('issue_date', $year)->count() + 1;

        return sprintf('INV-%d-%04d', $year, $sequence);
    }
}
