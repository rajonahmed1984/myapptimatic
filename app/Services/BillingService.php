<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;
use App\Models\Subscription;
use Carbon\Carbon;

class BillingService
{
    public function generateInvoiceForSubscription(Subscription $subscription, ?Carbon $issueDate = null): ?Invoice
    {
        if ($subscription->status !== 'active') {
            return null;
        }

        $subscription->loadMissing(['plan', 'customer']);

        $plan = $subscription->plan;
        $issueDate = ($issueDate ?? Carbon::today())->copy();

        $periodStart = Carbon::parse($subscription->current_period_start);
        $periodEnd = Carbon::parse($subscription->current_period_end);

        $subtotal = $this->calculateSubtotal($plan->interval, (float) $plan->price, $periodStart, $periodEnd);
        $dueDays = (int) Setting::getValue('invoice_due_days');
        $currency = (string) Setting::getValue('currency');
        $dueDate = $this->resolveDueDate($subscription, $issueDate, $plan->interval, $dueDays);

        $invoice = Invoice::create([
            'customer_id' => $subscription->customer_id,
            'subscription_id' => $subscription->id,
            'number' => $this->nextInvoiceNumber(),
            'status' => 'unpaid',
            'issue_date' => $issueDate->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'subtotal' => $subtotal,
            'late_fee' => 0,
            'total' => $subtotal,
            'currency' => strtoupper($currency),
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => sprintf(
                '%s (%s) %s to %s',
                $plan->name,
                $plan->interval,
                $periodStart->format('Y-m-d'),
                $periodEnd->format('Y-m-d')
            ),
            'quantity' => 1,
            'unit_price' => $subtotal,
            'line_total' => $subtotal,
        ]);

        [$nextStart, $nextEnd] = $this->nextPeriod($plan->interval, $periodEnd);
        $nextInvoiceAt = $this->nextInvoiceAt($nextStart, $nextEnd, $issueDate, $plan->interval);

        $subscription->update([
            'current_period_start' => $nextStart->toDateString(),
            'current_period_end' => $nextEnd->toDateString(),
            'next_invoice_at' => $nextInvoiceAt->toDateString(),
        ]);

        return $invoice;
    }

    public function recalculateInvoice(Invoice $invoice): ?Invoice
    {
        if (! $invoice->subscription) {
            return null;
        }

        $invoice->loadMissing(['subscription.plan', 'items']);
        $subscription = $invoice->subscription;
        $plan = $subscription->plan;

        if (! $plan) {
            return null;
        }

        [$periodStart, $periodEnd] = $this->invoicePeriod($invoice, $subscription, $plan->interval);

        $subtotal = $this->calculateSubtotal($plan->interval, (float) $plan->price, $periodStart, $periodEnd);
        $currency = (string) Setting::getValue('currency');

        $invoice->update([
            'subtotal' => $subtotal,
            'total' => $subtotal + (float) $invoice->late_fee,
            'currency' => strtoupper($currency),
        ]);

        $description = sprintf(
            '%s (%s) %s to %s',
            $plan->name,
            $plan->interval,
            $periodStart->format('Y-m-d'),
            $periodEnd->format('Y-m-d')
        );

        $item = $invoice->items->first();

        if ($item) {
            $item->update([
                'description' => $description,
                'unit_price' => $subtotal,
                'line_total' => $subtotal,
            ]);
        } else {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => $description,
                'quantity' => 1,
                'unit_price' => $subtotal,
                'line_total' => $subtotal,
            ]);
        }

        return $invoice;
    }

    private function calculateSubtotal(string $interval, float $price, Carbon $periodStart, Carbon $periodEnd): float
    {
        if ($interval !== 'monthly') {
            return round($price, 2);
        }

        if ($periodStart->isSameMonth($periodEnd) && $periodEnd->isLastOfMonth() && $periodStart->day !== 1) {
            $daysInPeriod = $periodStart->diffInDays($periodEnd) + 1;
            $daysInMonth = $periodStart->daysInMonth;
            $ratio = $daysInMonth > 0 ? ($daysInPeriod / $daysInMonth) : 1;

            return round($price * min(1, $ratio), 2);
        }

        return round($price, 2);
    }

    private function invoicePeriod(Invoice $invoice, Subscription $subscription, string $interval): array
    {
        if ($interval === 'monthly') {
            $firstInvoiceId = Invoice::query()
                ->where('subscription_id', $subscription->id)
                ->orderBy('issue_date')
                ->orderBy('id')
                ->value('id');

            if ($firstInvoiceId === $invoice->id) {
                $periodStart = Carbon::parse($subscription->start_date);
                $periodEnd = $periodStart->copy()->endOfMonth();

                return [$periodStart, $periodEnd];
            }

            $issueDate = Carbon::parse($invoice->issue_date);

            return [$issueDate->copy()->startOfMonth(), $issueDate->copy()->endOfMonth()];
        }

        $issueDate = Carbon::parse($invoice->issue_date);
        $periodStart = $issueDate->copy();
        $periodEnd = $interval === 'yearly'
            ? $periodStart->copy()->addYear()
            : $periodStart->copy()->addMonth();

        return [$periodStart, $periodEnd];
    }

    private function nextPeriod(string $interval, Carbon $currentEnd): array
    {
        if ($interval !== 'monthly') {
            $periodStart = $currentEnd->copy();
            $periodEnd = $interval === 'yearly'
                ? $periodStart->copy()->addYear()
                : $periodStart->copy()->addMonth();

            return [$periodStart, $periodEnd];
        }

        $periodStart = $currentEnd->copy()->addDay();
        $periodEnd = $periodStart->copy()->endOfMonth();

        return [$periodStart, $periodEnd];
    }

    private function nextInvoiceAt(Carbon $periodStart, Carbon $periodEnd, Carbon $today, string $interval): Carbon
    {
        if ($interval === 'monthly') {
            return $periodStart->copy();
        }

        $invoiceGenerationDays = (int) Setting::getValue('invoice_generation_days');
        $nextInvoiceAt = $invoiceGenerationDays > 0
            ? $periodEnd->copy()->subDays($invoiceGenerationDays)
            : $periodEnd->copy();

        if ($nextInvoiceAt->lessThan($today)) {
            $nextInvoiceAt = $periodEnd->copy();
        }

        return $nextInvoiceAt;
    }

    private function resolveDueDate(Subscription $subscription, Carbon $issueDate, string $interval, int $dueDays): Carbon
    {
        $hasInvoice = Invoice::query()
            ->where('subscription_id', $subscription->id)
            ->exists();

        if (! $hasInvoice) {
            return $issueDate->copy();
        }

        return $issueDate->copy()->addDays($dueDays);
    }

    public function nextInvoiceNumber(): string
    {
        $maxNumber = (int) Invoice::query()
            ->selectRaw('MAX(CAST(number AS UNSIGNED)) as max_number')
            ->value('max_number');

        $next = $maxNumber + 1;

        while (Invoice::query()->where('number', (string) $next)->exists()) {
            $next++;
        }

        return (string) $next;
    }
}
