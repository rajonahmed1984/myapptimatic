<?php

namespace App\Jobs;

use App\Enums\MailCategory;
use App\Models\SystemLog;
use App\Services\Mail\MailSender;
use App\Support\SystemLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class ResendSystemLogEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $systemLogId,
        public int $requestedBy,
        public string $requestIp,
        public int $idempotencyWindow
    ) {
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('email_resend_job_'.$this->systemLogId.'_'.$this->requestedBy.'_'.$this->idempotencyWindow))
                ->expireAfter(60)
                ->dontRelease(),
        ];
    }

    public function handle(MailSender $mailSender): void
    {
        $systemLog = SystemLog::query()->find($this->systemLogId);
        if (! $systemLog || $systemLog->category !== 'email') {
            return;
        }

        $context = $systemLog->context ?? [];
        $recipients = $context['to'] ?? [];
        $subject = (string) ($context['subject'] ?? '');
        $html = (string) ($context['html'] ?? '');
        $text = (string) ($context['text'] ?? '');

        if (empty($recipients) || $subject === '' || ($html === '' && $text === '')) {
            return;
        }

        $mailSender->sendHtmlText(
            MailCategory::SYSTEM,
            $recipients,
            $subject,
            $html !== '' ? $html : null,
            $text !== '' ? $text : null
        );

        SystemLogger::write('email', 'Email resent.', [
            'subject' => $subject,
            'to' => $recipients,
            'system_log_id' => $this->systemLogId,
        ], $this->requestedBy, $this->requestIp);
    }
}
