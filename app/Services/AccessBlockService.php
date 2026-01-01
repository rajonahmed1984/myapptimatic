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
    public function invoiceBlockStatus(?Customer $customer): array
    {
        if (! $customer) {
            return $this->emptyStatus();
        }

        $graceDays = (int) Setting::getValue('grace_period_days');
        $invoice = Invoice::query()
            ->with('customer')
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['unpaid', 'overdue'])
            ->orderBy('due_date')
            ->first();

        if (! $invoice) {
            return $this->emptyStatus();
        }

        $graceEnds = Carbon::parse($invoice->due_date)->addDays($graceDays)->endOfDay();
        $blocked = Carbon::now()->greaterThan($graceEnds);

        if ($customer->access_override_until?->isFuture()) {
            $blocked = false;
        }

        return [
            'blocked' => $blocked,
            'reason' => $blocked ? 'invoice_overdue' : 'invoice_due',
            'grace_ends_at' => $graceEnds->toDateTimeString(),
            'payment_url' => route('client.invoices.pay', $invoice),
            'invoice_id' => $invoice->id,
            'invoice_number' => is_numeric($invoice->number) ? $invoice->number : (string) $invoice->id,
            'invoice_status' => $invoice->status,
        ];
    }

    /**
     * Determine if the provided customer is currently access blocked.
     */
    public function isCustomerBlocked(?Customer $customer): bool
    {
        return $this->invoiceBlockStatus($customer)['blocked'];
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
