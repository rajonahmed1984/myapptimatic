<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Jobs\SendInvoiceCreatedNotifications;

class InvoiceObserver
{
    /**
     * Handle the Invoice "created" event.
     */
    public function created(Invoice $invoice): void
    {
        if ($invoice->status === 'unpaid') {
            SendInvoiceCreatedNotifications::dispatch($invoice->id);
        }
    }
}
