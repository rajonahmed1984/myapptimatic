<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\Branding;
use App\Support\UrlResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class AdminNotificationService
{
    public function sendNewOrder(Order $order, ?string $ipAddress = null): void
    {
        $recipients = $this->adminRecipients();
        if (empty($recipients)) {
            return;
        }

        $order->loadMissing([
            'customer',
            'plan.product',
            'invoice.items',
            'invoice.accountingEntries.paymentGateway',
            'invoice.paymentProofs.paymentGateway',
        ]);

        $template = EmailTemplate::query()
            ->where('key', 'new_order_notification')
            ->first();

        $companyName = Setting::getValue('company_name', config('app.name'));
        $orderNumber = $order->order_number ?? $order->id;
        $orderTotal = $order->invoice ? ($order->invoice->currency . ' ' . $order->invoice->total) : '--';
        $serviceName = $order->plan?->product
            ? $order->plan->product->name . ' - ' . $order->plan->name
            : ($order->plan?->name ?? '--');

        $subject = $template?->subject ?: "New order {$orderNumber} - {$companyName}";
        $body = $template?->body ?: '';
        $fromEmail = $this->resolveFromEmail($template);

        $replacements = [
            '{{client_name}}' => $order->customer?->name ?? '--',
            '{{client_email}}' => $order->customer?->email ?? '--',
            '{{company_name}}' => $companyName,
            '{{service_name}}' => $serviceName,
            '{{order_number}}' => $orderNumber,
            '{{order_total}}' => $orderTotal,
        ];

        $subject = $this->applyReplacements($subject, $replacements);
        $bodyHtml = $this->formatEmailBody($this->applyReplacements($body, $replacements));

        $logoUrl = Branding::url(Setting::getValue('company_logo_path'));
        $portalUrl = UrlResolver::portalUrl();
        $portalLoginUrl = $portalUrl.'/admin';
        $orderUrl = route('admin.orders.show', $order);
        $dateFormat = Setting::getValue('date_format', config('app.date_format', 'd-m-Y'));
        $timeZone = Setting::getValue('time_zone', config('app.timezone'));
        $paymentMethod = $this->resolvePaymentMethod($order);
        $ipAddress = $ipAddress ?: request()->ip() ?: '';
        $host = $this->resolveHost($ipAddress);

        $this->sendView(
            $recipients,
            'emails.new-order-notification',
            [
                'subject' => $subject,
                'companyName' => $companyName,
                'logoUrl' => $logoUrl,
                'portalUrl' => $portalUrl,
                'portalLoginUrl' => $portalLoginUrl,
                'portalLoginLabel' => 'log in to the admin area',
                'orderUrl' => $orderUrl,
                'dateFormat' => $dateFormat,
                'timeZone' => $timeZone,
                'order' => $order,
                'orderNumber' => $orderNumber,
                'serviceName' => $serviceName,
                'orderTotal' => $orderTotal,
                'paymentMethod' => $paymentMethod,
                'ipAddress' => $ipAddress,
                'host' => $host,
                'bodyHtml' => new HtmlString($bodyHtml),
            ],
            $subject,
            $fromEmail,
            $companyName
        );
    }

    public function sendOrderCancelled(Order $order): void
    {
        $recipients = $this->adminRecipients();
        if (empty($recipients)) {
            return;
        }

        $order->loadMissing(['customer', 'plan.product', 'invoice']);

        $template = EmailTemplate::query()
            ->where('key', 'order_cancelled_notification')
            ->first();

        $companyName = Setting::getValue('company_name', config('app.name'));
        $orderNumber = $order->order_number ?? $order->id;
        $orderTotal = $order->invoice ? ($order->invoice->currency . ' ' . $order->invoice->total) : '--';
        $serviceName = $order->plan?->product
            ? $order->plan->product->name . ' - ' . $order->plan->name
            : ($order->plan?->name ?? '--');

        $subject = $template?->subject ?: "Order cancelled {$orderNumber} - {$companyName}";
        $body = $template?->body ?: "Order {{order_number}} was cancelled.\nClient: {{client_name}} ({{client_email}})\nService: {{service_name}}\nOrder total: {{order_total}}\nOrder link: {{order_url}}";
        $fromEmail = $this->resolveFromEmail($template);

        $replacements = [
            '{{client_name}}' => $order->customer?->name ?? '--',
            '{{client_email}}' => $order->customer?->email ?? '--',
            '{{company_name}}' => $companyName,
            '{{service_name}}' => $serviceName,
            '{{order_number}}' => $orderNumber,
            '{{order_total}}' => $orderTotal,
            '{{order_url}}' => route('admin.orders.show', $order),
        ];

        $subject = $this->applyReplacements($subject, $replacements);
        $bodyHtml = $this->formatEmailBody($this->applyReplacements($body, $replacements));

        $this->sendGeneric($recipients, $subject, $bodyHtml, $fromEmail, $companyName);
    }

    public function sendInvoiceCreated(Invoice $invoice): void
    {
        $this->sendInvoiceTemplate($invoice, 'invoice_created', "Invoice {{invoice_number}} created - {{company_name}}");
    }

    public function sendInvoicePaid(Invoice $invoice): void
    {
        $this->sendInvoiceTemplate($invoice, 'invoice_payment_confirmation', "Payment received for invoice {{invoice_number}}");
    }

    public function sendInvoiceReminder(Invoice $invoice, string $templateKey): void
    {
        $this->sendInvoiceTemplate($invoice, $templateKey, "Reminder: invoice {{invoice_number}}");
    }

    public function sendOrderAccepted(Order $order): void
    {
        $recipients = $this->adminRecipients();
        if (empty($recipients)) {
            return;
        }

        $order->loadMissing(['customer', 'plan.product', 'approver']);

        $template = EmailTemplate::query()
            ->where('key', 'order_accepted_notification')
            ->first();

        $companyName = Setting::getValue('company_name', config('app.name'));
        $orderNumber = $order->order_number ?? $order->id;
        $subject = $template?->subject ?: "Order accepted #{{order_number}}";
        $body = $template?->body ?: "Order {{order_number}} was accepted.\n"
            . "Client: {{client_name}} ({{client_email}})\n"
            . "Order link: {{order_url}}";
        $fromEmail = $this->resolveFromEmail($template);
        $approver = $order->approver;

        $replacements = [
            '{{order_number}}' => $orderNumber,
            '{{client_name}}' => $order->customer?->name ?? '--',
            '{{client_email}}' => $order->customer?->email ?? '--',
            '{{company_name}}' => $companyName,
            '{{order_url}}' => route('admin.orders.show', $order),
            '{{approved_by}}' => $approver?->name ?? 'Admin',
        ];

        $subject = $this->applyReplacements($subject, $replacements);
        $bodyHtml = $this->formatEmailBody($this->applyReplacements($body, $replacements));

        $this->sendGeneric($recipients, $subject, $bodyHtml, $fromEmail, $companyName);
    }

    public function sendTicketCreated(SupportTicket $ticket): void
    {
        $this->sendTicketNotification($ticket, 'support_ticket_change_notification', 'New support ticket #{{ticket_id}}');
    }

    public function sendTicketReminder(SupportTicket $ticket): void
    {
        $this->sendTicketNotification($ticket, 'support_ticket_change_notification', 'Support ticket updated #{{ticket_id}}');
    }

    private function sendTicketNotification(SupportTicket $ticket, string $templateKey, string $fallbackSubject): void
    {
        $recipients = $this->adminRecipients();
        if (empty($recipients)) {
            return;
        }

        $ticket->loadMissing(['customer']);

        $template = EmailTemplate::query()
            ->where('key', $templateKey)
            ->first();

        $companyName = Setting::getValue('company_name', config('app.name'));
        $subject = $template?->subject ?: $fallbackSubject;
        $body = $template?->body ?: "Support ticket #{{ticket_id}} update.\nTicket: {{ticket_id}}\nSubject: {{ticket_subject}}\nStatus: {{ticket_status}}";
        $fromEmail = $this->resolveFromEmail($template);

        $replacements = [
            '{{ticket_id}}' => $ticket->id,
            '{{ticket_subject}}' => $ticket->subject,
            '{{ticket_status}}' => $ticket->status,
            '{{client_name}}' => $ticket->customer?->name ?? '--',
            '{{client_email}}' => $ticket->customer?->email ?? '--',
            '{{company_name}}' => $companyName,
            '{{ticket_url}}' => route('admin.support-tickets.show', $ticket),
        ];

        $subject = $this->applyReplacements($subject, $replacements);
        $bodyHtml = $this->formatEmailBody($this->applyReplacements($body, $replacements));

        $this->sendGeneric($recipients, $subject, $bodyHtml, $fromEmail, $companyName);
    }

    private function sendInvoiceTemplate(Invoice $invoice, string $templateKey, string $fallbackSubject): void
    {
        $recipients = $this->adminRecipients();
        if (empty($recipients)) {
            return;
        }

        $invoice->loadMissing(['customer']);

        $template = EmailTemplate::query()
            ->where('key', $templateKey)
            ->first();

        $companyName = Setting::getValue('company_name', config('app.name'));
        $invoiceNumber = is_numeric($invoice->number) ? $invoice->number : $invoice->id;
        $dateFormat = Setting::getValue('date_format', config('app.date_format', 'd-m-Y'));
        $paymentUrl = route('client.invoices.pay', $invoice);

        $replacements = [
            '{{client_name}}' => $invoice->customer?->name ?? '--',
            '{{client_email}}' => $invoice->customer?->email ?? '--',
            '{{company_name}}' => $companyName,
            '{{invoice_number}}' => $invoiceNumber,
            '{{invoice_total}}' => $invoice->currency.' '.$invoice->total,
            '{{invoice_due_date}}' => $invoice->due_date?->format($dateFormat) ?? '--',
            '{{payment_url}}' => $paymentUrl,
            '{{invoice_url}}' => route('admin.invoices.show', $invoice),
        ];

        $subject = $this->applyReplacements($template?->subject ?: $fallbackSubject, $replacements);
        $body = $template?->body ?: '';
        $bodyHtml = $this->formatEmailBody($this->applyReplacements($body, $replacements));
        $fromEmail = $this->resolveFromEmail($template);

        $this->sendGeneric($recipients, $subject, $bodyHtml, $fromEmail, $companyName);
    }

    private function adminRecipients(): array
    {
        $emails = User::query()
            ->where('role', 'admin')
            ->whereNotNull('email')
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($emails)) {
            $fallback = Setting::getValue('company_email') ?: config('mail.from.address');
            if ($fallback) {
                $emails = [$fallback];
            }
        }

        return $emails;
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

    private function sendGeneric(array $recipients, string $subject, string $bodyHtml, ?string $fromEmail, string $companyName): void
    {
        $logoUrl = Branding::url(Setting::getValue('company_logo_path'));
        $portalUrl = UrlResolver::portalUrl();
        $portalLoginUrl = $portalUrl.'/admin';

        $this->sendView(
            $recipients,
            'emails.generic',
            [
                'subject' => $subject,
                'companyName' => $companyName,
                'logoUrl' => $logoUrl,
                'portalUrl' => $portalUrl,
                'portalLoginUrl' => $portalLoginUrl,
                'portalLoginLabel' => 'log in to the admin area',
                'bodyHtml' => new HtmlString($bodyHtml),
            ],
            $subject,
            $fromEmail,
            $companyName
        );
    }

    private function sendView(
        array $recipients,
        string $view,
        array $data,
        string $subject,
        ?string $fromEmail,
        string $companyName
    ): void {
        try {
            Mail::send($view, $data, function ($message) use ($recipients, $subject, $fromEmail, $companyName) {
                $message->to($recipients)->subject($subject);
                if ($fromEmail) {
                    $message->from($fromEmail, $companyName);
                }
            });
        } catch (\Throwable $e) {
            Log::warning('Failed to send admin notification.', [
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
        }
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

    private function resolvePaymentMethod(Order $order): string
    {
        $invoice = $order->invoice;
        if (! $invoice) {
            return 'Pending';
        }

        $gateway = $invoice->accountingEntries
            ->first()?->paymentGateway
            ?? $invoice->paymentProofs->first()?->paymentGateway;

        return $gateway?->name ?: 'Pending';
    }

    private function resolveHost(string $ipAddress): string
    {
        if ($ipAddress === '') {
            return '--';
        }

        try {
            $host = gethostbyaddr($ipAddress);
            return $host ?: $ipAddress;
        } catch (\Throwable) {
            return $ipAddress;
        }
    }
}
