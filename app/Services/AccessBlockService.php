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
            return [
                'blocked' => true,
                'reason' => 'customer_inactive',
                'grace_ends_at' => null,
                'payment_url' => null,
                'invoice_id' => null,
                'invoice_number' => null,
                'invoice_status' => null,
            ];
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
        $graceEnds = Carbon::parse($invoice->due_date)->addDays($graceDays)->endOfDay();
        $isOverdue = Carbon::now()->greaterThan($graceEnds);
        $reason = $isOverdue ? 'invoice_overdue' : 'invoice_due';
        $isMarkedOverdue = (string) $invoice->status === 'overdue';
        $shouldBlock = $strictLicenseOverdue && ($isMarkedOverdue || $isOverdue);

        return [
            // Default behavior keeps portal access open; strict mode is used by license checks.
            'blocked' => $shouldBlock,
            'reason' => $reason,
            'grace_ends_at' => $graceEnds->toDateTimeString(),
            'payment_url' => route('client.invoices.pay', $invoice),
            'invoice_id' => $invoice->id,
            'invoice_number' => is_numeric($invoice->number) ? $invoice->number : (string) $invoice->id,
            'invoice_status' => $invoice->status,
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
        ];
    }
}
