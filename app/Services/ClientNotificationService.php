<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\License;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Support\Branding;
use App\Support\UrlResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ClientNotificationService
{
    public function sendTicketAutoClose(SupportTicket $ticket): void
    {
        $this->sendTicketTemplate($ticket, 'support_ticket_auto_close_notification');
    }

    public function sendTicketFeedback(SupportTicket $ticket): void
    {
        $this->sendTicketTemplate($ticket, 'support_ticket_feedback_request');
    }

    public function sendLicenseExpiryNotice(License $license, string $templateKey): void
    {
        $license->loadMissing(['subscription.customer', 'product']);
        $customer = $license->subscription?->customer;

        if (! $customer || ! $customer->email) {
            return;
        }

        $template = EmailTemplate::query()
            ->where('key', $templateKey)
            ->first();

        $companyName = Setting::getValue('company_name', config('app.name'));
        $subject = $template?->subject ?: 'License expiry notice - {{company_name}}';
        $body = $template?->body ?: '';

        $replacements = [
            '{{client_name}}' => $customer->name ?? '--',
            '{{client_email}}' => $customer->email ?? '--',
            '{{company_name}}' => $companyName,
            '{{license_key}}' => $license->license_key,
            '{{license_expires_at}}' => $license->expires_at?->format(
                Setting::getValue('date_format', config('app.date_format', 'd-m-Y'))
            ) ?? '--',
            '{{product_name}}' => $license->product?->name ?? '--',
        ];

        $subject = $this->applyReplacements($subject, $replacements);
        $bodyHtml = $this->formatEmailBody($this->applyReplacements($body, $replacements));
        $fromEmail = $this->resolveFromEmail($template);

        $this->sendGeneric($customer->email, $subject, $bodyHtml, $fromEmail, $companyName);
    }

    private function sendTicketTemplate(SupportTicket $ticket, string $templateKey): void
    {
        $ticket->loadMissing(['customer']);
        $customer = $ticket->customer;

        if (! $customer || ! $customer->email) {
            return;
        }

        $template = EmailTemplate::query()
            ->where('key', $templateKey)
            ->first();

        $companyName = Setting::getValue('company_name', config('app.name'));
        $subject = $template?->subject ?: 'Support ticket update - {{company_name}}';
        $body = $template?->body ?: '';

        $replacements = [
            '{{ticket_id}}' => $ticket->id,
            '{{ticket_subject}}' => $ticket->subject,
            '{{ticket_status}}' => $ticket->status,
            '{{ticket_url}}' => route('client.support-tickets.show', $ticket),
            '{{client_name}}' => $customer->name ?? '--',
            '{{company_name}}' => $companyName,
        ];

        $subject = $this->applyReplacements($subject, $replacements);
        $bodyHtml = $this->formatEmailBody($this->applyReplacements($body, $replacements));
        $fromEmail = $this->resolveFromEmail($template);

        $this->sendGeneric($customer->email, $subject, $bodyHtml, $fromEmail, $companyName);
    }

    private function sendGeneric(string $to, string $subject, string $bodyHtml, ?string $fromEmail, string $companyName): void
    {
        $logoUrl = Branding::url(Setting::getValue('company_logo_path'));
        $portalUrl = UrlResolver::portalUrl();
        $portalLoginUrl = $portalUrl.'/login';

        try {
            Mail::send('emails.generic', [
                'subject' => $subject,
                'companyName' => $companyName,
                'logoUrl' => $logoUrl,
                'portalUrl' => $portalUrl,
                'portalLoginUrl' => $portalLoginUrl,
                'portalLoginLabel' => 'log in to the client area',
                'bodyHtml' => new HtmlString($bodyHtml),
            ], function ($message) use ($to, $subject, $fromEmail, $companyName) {
                $message->to($to)->subject($subject);
                if ($fromEmail) {
                    $message->from($fromEmail, $companyName);
                }
            });
        } catch (\Throwable $e) {
            Log::warning('Failed to send client notification.', [
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveFromEmail(?EmailTemplate $template): ?string
    {
        $fromEmail = trim((string) ($template?->from_email ?? ''));

        if ($fromEmail === '') {
            $fromEmail = trim((string) Setting::getValue('company_email'));
        }

        if ($fromEmail === '') {
            $fromEmail = config('mail.from.address');
        }

        return $fromEmail ?: null;
    }

    private function applyReplacements(string $text, array $replacements): string
    {
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    private function formatEmailBody(string $body): string
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return '';
        }

        $looksLikeHtml = Str::contains($trimmed, ['<p', '<br', '<div', '<table', '<a ', '<strong', '<em', '<ul', '<ol', '<li']);

        if ($looksLikeHtml) {
            return $trimmed;
        }

        return nl2br(e($trimmed));
    }
}
