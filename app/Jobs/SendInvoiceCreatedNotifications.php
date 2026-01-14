<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\AdminNotificationService;
use App\Services\ClientNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendInvoiceCreatedNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $invoiceId)
    {
    }

    public function handle(
        AdminNotificationService $adminNotifications,
        ClientNotificationService $clientNotifications
    ): void {
        $invoice = Invoice::find($this->invoiceId);

        if (! $invoice) {
            return;
        }

        $adminNotifications->sendInvoiceCreated($invoice);
        $clientNotifications->sendInvoiceCreated($invoice);
    }
}
