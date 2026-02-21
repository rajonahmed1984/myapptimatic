<?php

namespace App\Mail;

use App\Enums\MailCategory;
use App\Mail\Concerns\UsesMailCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LicenseSyncReportMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    use UsesMailCategory;

    public function __construct(public array $payload)
    {
    }

    public function mailCategory(): string
    {
        return MailCategory::SYSTEM;
    }

    public function build(): self
    {
        return $this->withMailableCategoryHeader()
            ->subject($this->payload['subject'] ?? 'License Synchronisation Report')
            ->view('emails.license-sync-report', $this->payload);
    }
}
