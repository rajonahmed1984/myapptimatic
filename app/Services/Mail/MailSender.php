<?php

namespace App\Services\Mail;

use App\Enums\MailCategory;
use App\Support\MailCategoryContext;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class MailSender
{
    public function sendView(
        string $category,
        array|string $to,
        string $view,
        array $data,
        string $subject,
        array $attachments = []
    ): void {
        $recipients = $this->normalizeRecipients($to);

        MailCategoryContext::run($category, function () use ($recipients, $view, $data, $subject, $attachments) {
            Mail::send($view, $data, function ($message) use ($recipients, $subject, $attachments) {
                $message->to($recipients)->subject($subject);

                foreach ($attachments as $attachment) {
                    if (is_array($attachment) && isset($attachment['data'], $attachment['filename'])) {
                        $message->attachData(
                            $attachment['data'],
                            $attachment['filename'],
                            ['mime' => $attachment['mimetype'] ?? 'application/pdf']
                        );
                    }
                }
            });
        });
    }

    public function sendRaw(string $category, array|string $to, string $text, string $subject): void
    {
        $recipients = $this->normalizeRecipients($to);

        MailCategoryContext::run($category, function () use ($recipients, $text, $subject) {
            Mail::raw($text, function ($message) use ($recipients, $subject) {
                $message->to($recipients)->subject($subject);
            });
        });
    }

    public function sendHtmlText(
        string $category,
        array|string $to,
        string $subject,
        ?string $html,
        ?string $text
    ): void {
        $recipients = $this->normalizeRecipients($to);
        $html = (string) ($html ?? '');
        $text = (string) ($text ?? '');

        MailCategoryContext::run($category, function () use ($recipients, $subject, $html, $text) {
            Mail::send([], [], function ($message) use ($recipients, $subject, $html, $text) {
                $message->to($recipients)->subject($subject);
                if ($html !== '') {
                    $message->html($html);
                }
                if ($text !== '') {
                    $message->text($text);
                }
            });
        });
    }

    public function sendMailable(
        string $category,
        array|string $to,
        Mailable $mailable,
        bool $queue = false
    ): void {
        $recipients = $this->normalizeRecipients($to);

        MailCategoryContext::run($category, function () use ($recipients, $mailable, $queue) {
            $pending = Mail::to($recipients);

            if ($queue) {
                $pending->queue($mailable);
                return;
            }

            if (method_exists($pending, 'sendNow')) {
                $pending->sendNow($mailable);
                return;
            }

            $pending->send($mailable);
        });
    }

    /**
     * @param array<int, string>|string $to
     * @return array<int, string>|string
     */
    private function normalizeRecipients(array|string $to): array|string
    {
        if (! is_array($to)) {
            return trim($to);
        }

        return collect($to)
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}

