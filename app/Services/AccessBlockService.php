<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Setting;
use Carbon\Carbon;
use function route;

class AccessBlockService
{
    /**
     * Return the latest invoice block status for the provided customer.
     */
    public function invoiceBlockStatus(?Customer $customer, bool $strictLicenseOverdue = false): array
    {
        if (! $customer) {
            return $this->emptyStatus();
        }

        if ((string) $customer->status !== 'active') {
            return array_merge($this->emptyStatus(), [
                'blocked' => true,
                'reason' => 'customer_inactive',
            ]);
        }

        $graceDays = (int) Setting::getValue('grace_period_days');
        $invoiceQuery = Invoice::query()
            ->with('customer')
            ->where('customer_id', $customer->id);

        $invoice = null;

        if ($strictLicenseOverdue) {
            $invoice = (clone $invoiceQuery)
                ->where('status', 'overdue')
                ->orderBy('due_date')
                ->first();
        }

        if (! $invoice) {
            $invoice = (clone $invoiceQuery)
                ->whereIn('status', ['unpaid', 'overdue'])
                ->orderBy('due_date')
                ->first();
        }

        if (! $invoice) {
            return $this->emptyStatus();
        }

        return $this->buildStatus($invoice, $graceDays, $strictLicenseOverdue);
    }

    /**
     * Determine if the provided customer is currently access blocked.
     */
    public function isCustomerBlocked(?Customer $customer, bool $strictLicenseOverdue = false): bool
    {
        return $this->invoiceBlockStatus($customer, $strictLicenseOverdue)['blocked'];
    }

    private function buildStatus(Invoice $invoice, int $graceDays, bool $strictLicenseOverdue): array
    {
        $now = Carbon::now();
        $today = Carbon::today();
        $dueDate = Carbon::parse($invoice->due_date)->startOfDay();
        $graceEnds = $dueDate->copy()->addDays($graceDays)->endOfDay();
        $isGraceExpired = $now->greaterThan($graceEnds);
        $isMarkedOverdue = (string) $invoice->status === 'overdue';
        $overdueDays = $dueDate->lessThan($today)
            ? $dueDate->diffInDays($today)
            : 0;

        if ($overdueDays === 0 && $isMarkedOverdue) {
            $overdueDays = 1;
        }

        $isOverdue = $isMarkedOverdue || $overdueDays > 0;
        $reason = $isOverdue ? 'invoice_overdue' : 'invoice_due';
        $shouldBlock = $strictLicenseOverdue && ($isMarkedOverdue || $isGraceExpired);
        $amount = $this->resolveInvoiceAmount($invoice);
        $amountDisplay = $this->formatInvoiceAmount($amount, (string) $invoice->currency);
        $dueDateDisplay = $dueDate->format('F j, Y');

        return [
            // Default behavior keeps portal access open; strict mode is used by license checks.
            'blocked' => $shouldBlock,
            'reason' => $reason,
            'grace_ends_at' => $graceEnds->toDateTimeString(),
            'payment_url' => route('client.invoices.pay', $invoice),
            'invoice_id' => $invoice->id,
            'invoice_number' => is_numeric($invoice->number) ? $invoice->number : (string) $invoice->id,
            'invoice_status' => $invoice->status,
            'invoice_due_date' => $dueDate->toDateString(),
            'invoice_due_date_display' => $dueDateDisplay,
            'invoice_amount' => $amount,
            'invoice_amount_display' => $amountDisplay,
            'invoice_overdue_days' => $overdueDays,
            'notice_message' => $this->buildNoticeMessage($dueDateDisplay, $amountDisplay, $overdueDays),
            'notice_severity' => $this->resolveNoticeSeverity($overdueDays),
        ];
    }

    private function emptyStatus(): array
    {
        return [
            'blocked' => false,
            'reason' => null,
            'grace_ends_at' => null,
            'payment_url' => null,
            'invoice_id' => null,
            'invoice_number' => null,
            'invoice_status' => null,
            'invoice_due_date' => null,
            'invoice_due_date_display' => null,
            'invoice_amount' => null,
            'invoice_amount_display' => null,
            'invoice_overdue_days' => 0,
            'notice_message' => null,
            'notice_severity' => null,
        ];
    }

    private function resolveInvoiceAmount(Invoice $invoice): ?float
    {
        $rawAmount = $invoice->total;
        if ($rawAmount === null) {
            return null;
        }

        $amount = round((float) $rawAmount, 2);

        return $amount > 0 ? $amount : null;
    }

    private function formatInvoiceAmount(?float $amount, string $currency): ?string
    {
        if ($amount === null) {
            return null;
        }

        $code = strtoupper(trim($currency));
        if ($code === '') {
            $code = strtoupper((string) Setting::getValue('currency', ''));
        }

        $label = in_array($code, ['BDT', 'TK'], true)
            ? 'Tk'
            : ($code !== '' ? $code : '$');

        return trim($label.' '.number_format($amount, 2, '.', ''));
    }

    private function buildNoticeMessage(string $dueDateDisplay, ?string $amountDisplay, int $overdueDays): string
    {
        $message = $amountDisplay
            ? "The invoice due date is {$dueDateDisplay} and the amount is {$amountDisplay}."
            : "The invoice due date is {$dueDateDisplay}.";

        if ($overdueDays === 1) {
            return $message.' 2 days to go to avoid suspension.';
        }

        if ($overdueDays === 2) {
            return $message.' 1 day to go to avoid suspension.';
        }

        if ($overdueDays >= 3) {
            return $message.' Today is the last day to clear payment and avoid suspension.';
        }

        return $message;
    }

    private function resolveNoticeSeverity(int $overdueDays): string
    {
        if ($overdueDays >= 3) {
            return 'critical';
        }

        if ($overdueDays >= 1) {
            return 'rose';
        }

        return 'amber';
    }
}
