<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\Sms\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendInvoiceSmsNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const EVENT_CREATED = 'created';

    public const EVENT_PAID = 'paid';

    // A retry after a timeout could deliver the same SMS twice, and the
    // customer pays for neither — one attempt, failures land in the system log.
    public int $tries = 1;

    public function __construct(
        public int $invoiceId,
        public string $event
    ) {
        // Invoices are often created (and totalled) inside a transaction; wait
        // for it so the message carries the final amount.
        $this->afterCommit();
    }

    public function handle(SmsService $sms): void
    {
        $invoice = Invoice::find($this->invoiceId);

        if (! $invoice) {
            return;
        }

        match ($this->event) {
            self::EVENT_CREATED => $sms->sendInvoiceCreated($invoice),
            self::EVENT_PAID => $sms->sendInvoicePaid($invoice),
            default => null,
        };
    }
}
