<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\AdminNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendInvoiceReminderNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $invoiceId,
        public string $templateKey
    ) {
    }

    public function handle(AdminNotificationService $adminNotifications): void
    {
        $invoice = Invoice::find($this->invoiceId);

        if (! $invoice) {
            return;
        }

        $adminNotifications->sendInvoiceReminder($invoice, $this->templateKey);
    }
}
