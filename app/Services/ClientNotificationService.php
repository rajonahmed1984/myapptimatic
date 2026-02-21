<?php

namespace App\Services;

use App\Enums\MailCategory;
use App\Models\Customer;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Support\Branding;
use App\Support\UrlResolver;
use App\Services\Mail\MailSender;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ClientNotificationService
{
    public function __construct(
        private readonly MailSender $mailSender
    ) {
    }

    public function sendClientSignup(Customer $customer): void
    {
        if (! $customer->email) {
            return;
        }

        $template = EmailTemplate::query()
            ->where('key', 'client_signup_email')
            ->first();

        $companyName = Setting::getValue('company_name', config('app.name'));
        $subject = $template?->subject ?: "Welcome to {$companyName}";
        $body = $template?->body ?: "Hi {{client_name}},\n\nYour account for {{company_name}} is ready.\nLogin: {{login_url}}\nEmail: {{client_email}}\n\nThank you,\n{{company_name}}";
        $loginUrl = UrlResolver::portalUrl() . '/login';
        $fromEmail = $this->resolveFromEmail($template);

        $replacements = [
            '{{client_name}}' => $customer->name,
            '{{client_email}}' => $customer->email,
            '{{company_name}}' => $companyName,
            '{{login_url}}' => $loginUrl,
        ];

        $subject = $this->applyReplacements($subject, $replacements);
        $bodyHtml = $this->formatEmailBody($this->applyReplacements($body, $replacements));

        $this->sendGeneric($customer->email, $subject, $bodyHtml, $fromEmail, $companyName, [], MailCategory::SYSTEM);
    }

    public function sendOrderConfirmation(Order $order): void
    {
        $order->loadMissing(['customer', 'plan.product', 'invoice']);

        $recipient = $order->customer?->email;
        if (! $recipient) {
            return;
        }

        $template = EmailTemplate::query()
            ->where('key', 'order_confirmation')
            ->first();

        $companyName = Setting::getValue('company_name', config('app.name'));
        $orderNumber = $order->order_number ?? $order->id;
        $orderTotal = $order->invoice ? ($order->invoice->currency . ' ' . $order->invoice->total) : '--';
        $serviceName = $order->plan?->product
            ? $order->plan->product->name . ' - ' . $order->plan->name
            : ($order->plan?->name ?? '--');
        $fromEmail = $this->resolveFromEmail($template);

        $subject = $template?->subject ?: "Order {$orderNumber} confirmed - {$companyName}";
        $body = $template?->body ?: "Hi {{client_name}},\n\nWe received your order for {{service_name}}.\nOrder number: {{order_number}}\nOrder total: {{order_total}}\n\nThank you,\n{{company_name}}";

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
        $attachment = $this->invoiceAttachment($order->invoice);

        $this->sendGeneric(
            $recipient,
            $subject,
            $bodyHtml,
            $fromEmail,
            $companyName,
            $attachment ? [$attachment] : [],
            MailCategory::BILLING
        );
    }

    public function sendOrderAccepted(Order $order): void
    {
        $order->loadMissing(['customer', 'plan.product', 'invoice', 'subscription.licenses.domains']);

        $customer = $order->customer;
        if (! $customer || ! $customer->email) {
            return;
        }

        $template = EmailTemplate::query()
            ->where('key', 'order_accepted_confirmation')
            ->first();

        $companyName = Setting::getValue('company_name', config('app.name'));
        $orderNumber = $order->order_number ?? $order->id;
        $serviceName = $order->plan?->product
            ? ($order->plan->product->name . ' - ' . ($order->plan->name ?? '--'))
            : ($order->plan?->name ?? '--');
        $invoiceNumber = $order->invoice
            ? (is_numeric($order->invoice->number) ? $order->invoice->number : $order->invoice->id)
            : '--';
        $invoiceUrl = $order->invoice ? route('client.invoices.show', $order->invoice) : '--';
        $license = $order->subscription?->licenses->sortByDesc('id')->first();
        $licenseKey = $license?->license_key ?: '--';
        $licenseDomain = $license?->domains->first()?->domain ?? '--';
        $fromEmail = $this->resolveFromEmail($template);

        $subject = $template?->subject ?: "Your order {$orderNumber} has been accepted";
        $body = $template?->body ?: "Hi {{client_name}},\n\n"
            . "Your order {{order_number}} for {{service_name}} has been accepted.\n"
            . "License key: {{license_key}}\n"
            . "Domain: {{license_domain}}\n"
            . "Invoice: {{invoice_number}}\n"
            . "View invoice: {{invoice_url}}\n\n"
            . "Thank you,\n{{company_name}}";

        $replacements = [
            '{{client_name}}' => $customer->name ?? '--',
            '{{order_number}}' => $orderNumber,
            '{{service_name}}' => $serviceName,
            '{{license_key}}' => $licenseKey,
            '{{license_domain}}' => $licenseDomain,
            '{{invoice_number}}' => $invoiceNumber,
            '{{invoice_url}}' => $invoiceUrl,
            '{{company_name}}' => $companyName,
        ];

        $subject = $this->applyReplacements($subject, $replacements);
        $bodyHtml = $this->formatEmailBody($this->applyReplacements($body, $replacements));

        $this->sendGeneric($customer->email, $subject, $bodyHtml, $fromEmail, $companyName, [], MailCategory::BILLING);
    }

    public function sendInvoiceCreated(Invoice $invoice): void
    {
        $invoice->loadMissing(['customer']);

        $recipient = $invoice->customer?->email;
        if (! $recipient) {
            return;
        }

        $template = EmailTemplate::query()
            ->where('key', 'invoice_created')
            ->first();

        $companyName = Setting::getValue('company_name', config('app.name'));
        $invoiceNumber = is_numeric($invoice->number) ? $invoice->number : $invoice->id;
        $dateFormat = Setting::getValue('date_format', config('app.date_format', 'd-m-Y'));
        $dueDate = $invoice->due_date?->format($dateFormat) ?? '--';
        $paymentUrl = route('client.invoices.pay', $invoice);
        $fromEmail = $this->resolveFromEmail($template);

        $subject = $template?->subject ?: "Invoice {$invoiceNumber} created - {$companyName}";
        $body = $template?->body ?: "Hi {{client_name}},\n\nA new invoice has been created.\nInvoice: {{invoice_number}}\nTotal: {{invoice_total}}\nDue date: {{invoice_due_date}}\n\nPay here: {{payment_url}}";

        $replacements = [
            '{{client_name}}' => $invoice->customer?->name ?? '--',
            '{{client_email}}' => $invoice->customer?->email ?? '--',
            '{{company_name}}' => $companyName,
            '{{invoice_number}}' => $invoiceNumber,
            '{{invoice_total}}' => $invoice->currency.' '.$invoice->total,
            '{{invoice_due_date}}' => $dueDate,
            '{{payment_url}}' => $paymentUrl,
        ];

        $subject = $this->applyReplacements($subject, $replacements);
        $bodyHtml = $this->formatEmailBody($this->applyReplacements($body, $replacements));
        $attachment = $this->invoiceAttachment($invoice);

        $this->sendGeneric(
            $recipient,
            $subject,
            $bodyHtml,
            $fromEmail,
            $companyName,
            $attachment ? [$attachment] : [],
            MailCategory::BILLING
        );
    }

    public function sendManualPaymentSubmission(PaymentProof $paymentProof): void
    {
        $paymentProof->loadMissing(['customer', 'invoice.paymentAttempts']);
        $customer = $paymentProof->customer ?? $paymentProof->invoice?->customer;
        $invoice = $paymentProof->invoice;

        if (! $customer || ! $customer->email || ! $invoice) {
            return;
        }

        $template = EmailTemplate::query()
            ->where('key', 'manual_payment_submission')
            ->first();

        $companyName = Setting::getValue('company_name', config('app.name'));
        $invoiceNumber = is_numeric($invoice->number) ? $invoice->number : $invoice->id;
        $paymentAmount = $paymentProof->amount;
        $paymentReference = $paymentProof->reference ?? $paymentProof->id;
        $paymentUrl = route('client.invoices.show', $invoice);
        $gatewayName = $paymentProof->paymentGateway->name ?? 'Manual';
        $fromEmail = $this->resolveFromEmail($template);

        $subject = $template?->subject ?: "Payment submitted for invoice {$invoiceNumber}";
        $body = $template?->body ?: "Hi {{client_name}},\n\nWe received your manual payment submission for invoice {{invoice_number}} ({{payment_amount}}). We'll review it and update the invoice soon.\n\nReference: {{payment_reference}}\nPayment method: {{payment_gateway}}\n\nThanks,\n{{company_name}}";

        $replacements = [
            '{{client_name}}' => $customer->name,
            '{{invoice_number}}' => $invoiceNumber,
            '{{payment_amount}}' => $paymentAmount,
            '{{payment_reference}}' => $paymentReference,
            '{{payment_gateway}}' => $gatewayName,
            '{{payment_url}}' => $paymentUrl,
            '{{company_name}}' => $companyName,
        ];

        $subject = $this->applyReplacements($subject, $replacements);
        $bodyHtml = $this->formatEmailBody($this->applyReplacements($body, $replacements));

        $this->sendGeneric($customer->email, $subject, $bodyHtml, $fromEmail, $companyName, [], MailCategory::BILLING);
    }

    public function sendInvoicePaymentConfirmation(Invoice $invoice, ?string $reference = null): void
    {
        $invoice->loadMissing(['customer']);
        $customer = $invoice->customer;

        if (! $customer || ! $customer->email) {
            return;
        }

        $template = EmailTemplate::query()
            ->where('key', 'client_invoice_payment_confirmation')
            ->first();

        $companyName = Setting::getValue('company_name', config('app.name'));
        $invoiceNumber = is_numeric($invoice->number) ? $invoice->number : $invoice->id;
        $paymentUrl = route('client.invoices.show', $invoice);
        $fromEmail = $this->resolveFromEmail($template);

        $subject = $template?->subject ?: "Payment received for invoice {$invoiceNumber}";
        $body = $template?->body ?: "Hi {{client_name}},\n\nThank you for your payment. Invoice {{invoice_number}} is now marked as paid.\n\nReference: {{payment_reference}}\nView invoice: {{payment_url}}\n\nWarm regards,\n{{company_name}}";

        $replacements = [
            '{{client_name}}' => $customer->name,
            '{{invoice_number}}' => $invoiceNumber,
            '{{invoice_total}}' => $invoice->currency.' '.$invoice->total,
            '{{payment_reference}}' => $reference ?? '--',
            '{{payment_url}}' => $paymentUrl,
            '{{company_name}}' => $companyName,
        ];

        $subject = $this->applyReplacements($subject, $replacements);
        $bodyHtml = $this->formatEmailBody($this->applyReplacements($body, $replacements));

        $this->sendGeneric($customer->email, $subject, $bodyHtml, $fromEmail, $companyName, [], MailCategory::BILLING);
    }

    public function sendTicketAutoClose(SupportTicket $ticket): void
    {
        $this->sendTicketTemplate($ticket, 'support_ticket_auto_close_notification', 'Support ticket auto-closed - {{company_name}}');
    }

    public function sendTicketFeedback(SupportTicket $ticket): void
    {
        $this->sendTicketTemplate($ticket, 'support_ticket_feedback_request', 'Support ticket feedback requested - {{company_name}}');
    }

    public function sendTicketReplyFromAdmin(SupportTicket $ticket, SupportTicketReply $reply): void
    {
        $attachmentUrl = $reply->attachmentUrl();
        $extra = [
            '{{reply_message}}' => $reply->message,
            '{{admin_name}}' => $reply->user?->name ?? 'Admin',
            '{{reply_attachment_url}}' => $attachmentUrl ?? '',
            '{{reply_attachment_name}}' => $reply->attachmentName() ?? '',
        ];

        $this->sendTicketTemplate(
            $ticket,
            'support_ticket_reply',
            'Reply on ticket #{{ticket_id}}',
            $extra
        );
    }

    public function sendTicketOpened(SupportTicket $ticket): void
    {
        $this->sendTicketTemplate($ticket, 'support_ticket_opened', 'Support ticket opened - {{company_name}}');
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

        $this->sendGeneric($customer->email, $subject, $bodyHtml, $fromEmail, $companyName, [], MailCategory::BILLING);
    }

    private function sendTicketTemplate(SupportTicket $ticket, string $templateKey, string $fallbackSubject, array $extraReplacements = []): void
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
        $subject = $template?->subject ?: $fallbackSubject;
        $body = $template?->body ?: '';

        $replacements = array_merge([
            '{{ticket_id}}' => $ticket->id,
            '{{ticket_subject}}' => $ticket->subject,
            '{{ticket_message}}' => $ticket->message ?? '--',
            '{{ticket_status}}' => $ticket->status,
            '{{ticket_url}}' => route('client.support-tickets.show', $ticket),
            '{{client_name}}' => $customer->name ?? '--',
            '{{company_name}}' => $companyName,
        ], $extraReplacements);

        $subject = $this->applyReplacements($subject, $replacements);
        $bodyHtml = $this->formatEmailBody($this->applyReplacements($body, $replacements));
        $fromEmail = $this->resolveFromEmail($template);

        $this->sendGeneric($customer->email, $subject, $bodyHtml, $fromEmail, $companyName, [], MailCategory::SUPPORT);
    }

    private function sendGeneric(
        string $to,
        string $subject,
        string $bodyHtml,
        ?string $fromEmail,
        string $companyName,
        array $attachments = [],
        string $category = MailCategory::SYSTEM
    ): void
    {
        $logoUrl = Branding::url(Setting::getValue('company_logo_path'));
        $portalUrl = UrlResolver::portalUrl();
        $portalLoginUrl = $portalUrl.'/login';

        try {
            $this->mailSender->sendView($category, $to, 'emails.generic', [
                'subject' => $subject,
                'companyName' => $companyName,
                'logoUrl' => $logoUrl,
                'portalUrl' => $portalUrl,
                'portalLoginUrl' => $portalLoginUrl,
                'portalLoginLabel' => 'log in to the client area',
                'bodyHtml' => new HtmlString($bodyHtml),
            ], $subject, $attachments);
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

    private function invoiceAttachment(Invoice $invoice): ?array
    {
        if (! $invoice) {
            return null;
        }

        $invoice->loadMissing([
            'items',
            'customer',
            'subscription.plan.product',
            'accountingEntries.paymentGateway',
        ]);

        $html = view('client.invoices.pdf', [
            'invoice' => $invoice,
            'payToText' => Setting::getValue('pay_to_text'),
            'companyEmail' => Setting::getValue('company_email'),
        ])->render();

        $pdf = app('dompdf.wrapper')->loadHTML($html);
        $number = is_numeric($invoice->number) ? $invoice->number : $invoice->id;

        return [
            'data' => $pdf->output(),
            'filename' => 'invoice-'.$number.'.pdf',
            'mimetype' => 'application/pdf',
        ];
    }
}
