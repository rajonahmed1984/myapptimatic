<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Support\SystemLogger;
use App\Support\Currency;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MilestoneInvoiceService
{
    /**
     * Generate two invoices (advance/final) for an order based on percentages.
     * Does not change existing invoices; creates new ones.
     */
    public function createMilestones(
        Order $order,
        int $advancePercent,
        int $finalPercent,
        Carbon $advanceDue,
        Carbon $finalDue
    ): array {
        if ($advancePercent + $finalPercent !== 100) {
            throw ValidationException::withMessages([
                'advance_percent' => 'Advance and final percentages must sum to 100.',
            ]);
        }

        $baseTotal = $this->resolveOrderAmount($order);
        if ($baseTotal === null) {
            throw ValidationException::withMessages([
                'amount' => 'Unable to determine order amount. Ensure a base invoice or plan price exists.',
            ]);
        }

        $advanceTotal = round($baseTotal * ($advancePercent / 100), 2);
        $finalTotal = round($baseTotal * ($finalPercent / 100), 2);

        $advanceDescription = sprintf('Advance payment (%d%%) for order %s', $advancePercent, $order->order_number ?? $order->id);
        $finalDescription = sprintf('Final payment (%d%%) for order %s', $finalPercent, $order->order_number ?? $order->id);

        return DB::transaction(function () use ($order, $advanceTotal, $finalTotal, $advanceDue, $finalDue, $advanceDescription, $finalDescription) {
            $advance = $this->createInvoice($order, $advanceTotal, $advanceDue, $advanceDescription);
            $final = $this->createInvoice($order, $finalTotal, $finalDue, $finalDescription);

            SystemLogger::write('module', 'Milestone invoices created.', [
                'order_id' => $order->id,
                'advance_invoice_id' => $advance->id,
                'final_invoice_id' => $final->id,
                'customer_id' => $order->customer_id,
            ]);

            return [$advance, $final];
        });
    }

    private function resolveOrderAmount(Order $order): ?float
    {
        if ($order->invoice && $order->invoice->total > 0) {
            return (float) $order->invoice->total;
        }

        if ($order->plan && $order->plan->price > 0) {
            return (float) $order->plan->price;
        }

        return null;
    }

    private function createInvoice(Order $order, float $amount, Carbon $dueDate, string $description): Invoice
    {
        $today = Carbon::today();

        // Determine currency with fallback and validation
        $currency = $order->invoice->currency ?? Currency::DEFAULT;
        if (!Currency::isAllowed($currency)) {
            $currency = Currency::DEFAULT;
        }

        $invoice = Invoice::create([
            'customer_id' => $order->customer_id,
            'subscription_id' => $order->subscription_id,
            'status' => 'unpaid',
            'issue_date' => $today->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'subtotal' => $amount,
            'late_fee' => 0,
            'total' => $amount,
            'currency' => $currency,
            'notes' => $description,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => $description,
            'quantity' => 1,
            'unit_price' => $amount,
            'total' => $amount,
        ]);

        return $invoice;
    }
}
