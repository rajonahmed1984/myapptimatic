<?php

namespace App\Observers;

use App\Jobs\SendInvoiceCreatedNotifications;
use App\Jobs\SendInvoiceSmsNotification;
use App\Models\Invoice;

class InvoiceObserver
{
    /**
     * Handle the Invoice "created" event.
     */
    public function created(Invoice $invoice): void
    {
        if ($invoice->status === 'unpaid') {
            SendInvoiceCreatedNotifications::dispatch($invoice->id);
            SendInvoiceSmsNotification::dispatch($invoice->id, SendInvoiceSmsNotification::EVENT_CREATED);
        }
    }

    /**
     * Handle the Invoice "updated" event.
     *
     * Invoices reach "paid" from many places (gateway callback, payment proof,
     * admin mark-paid / add-payment / status edit, accounting, sales rep
     * collection), so the paid SMS hangs off the status change itself.
     */
    public function updated(Invoice $invoice): void
    {
        if ($invoice->wasChanged('status') && $invoice->status === 'paid') {
            SendInvoiceSmsNotification::dispatch($invoice->id, SendInvoiceSmsNotification::EVENT_PAID);
        }
    }
}
