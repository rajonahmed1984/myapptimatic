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
        $bodyHtml = $this->formatEmailBody($body, $replacements);

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
        $bodyHtml = $this->formatEmailBody($body, $replacements);
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
        $bodyHtml = $this->formatEmailBody($body, $replacements);

        $this->sendGeneric($customer->email, $subject, $bodyHtml, $fromEmail, $companyName, [], MailCategory::BILLING);
    }

    public function sendTransferInvite(\App\Models\OwnershipTransfer $transfer, string $plainToken): void
    {
        $transfer->loadMissing(['project', 'subscription.plan.product', 'fromCustomer', 'toCustomer.users']);

        $recipients = $transfer->toCustomer?->users
            ?->where('role', \App\Enums\Role::CLIENT)
            ->pluck('email')
            ->filter();

        if (! $recipients || $recipients->isEmpty()) {
            return;
        }

        $template = EmailTemplate::query()->where('key', 'ownership_transfer_invite')->first();
        $companyName = Setting::getValue('company_name', config('app.name'));
        $dateFormat = Setting::getValue('date_format', config('app.date_format', 'd-m-Y'));
        $confirmUrl = route('client.transfers.confirm', ['transfer' => $transfer->id, 'token' => $plainToken]);
        $fromEmail = $this->resolveFromEmail($template);

        $subject = $template?->subject ?: "You've been invited to receive a transfer - {$companyName}";
        $body = $template?->body ?: "Hi,\n\n{{from_customer_name}} wants to transfer \"{{project_name}}\" to your account.\n\nReview and respond: {{confirm_url}}\n\nThis invite expires on {{expires_at}}.\n\n{{company_name}}";

        $replacements = [
            '{{from_customer_name}}' => $transfer->fromCustomer?->name ?? '--',
            '{{project_name}}' => $this->transferSubjectLabel($transfer),
            '{{confirm_url}}' => $confirmUrl,
            '{{expires_at}}' => $transfer->token_expires_at?->format($dateFormat) ?? '--',
            '{{company_name}}' => $companyName,
        ];

        $subject = $this->applyReplacements($subject, $replacements);
        $bodyHtml = $this->formatEmailBody($body, $replacements);

        foreach ($recipients as $email) {
            $this->sendGeneric($email, $subject, $bodyHtml, $fromEmail, $companyName, [], MailCategory::SYSTEM);
        }
    }

    public function sendTransferAccepted(\App\Models\OwnershipTransfer $transfer): void
    {
        $transfer->loadMissing(['project', 'subscription.plan.product', 'toCustomer', 'fromCustomer.users']);

        $recipients = $transfer->fromCustomer?->users
            ?->where('role', \App\Enums\Role::CLIENT)
            ->pluck('email')
            ->filter();

        if (! $recipients || $recipients->isEmpty()) {
            return;
        }

        $template = EmailTemplate::query()->where('key', 'ownership_transfer_accepted')->first();
        $companyName = Setting::getValue('company_name', config('app.name'));
        $fromEmail = $this->resolveFromEmail($template);

        $subject = $template?->subject ?: "Your transfer request was accepted - {$companyName}";
        $body = $template?->body ?: "Hi,\n\n{{to_customer_name}} accepted your transfer of \"{{project_name}}\".\n\n{{company_name}}";

        $replacements = [
            '{{to_customer_name}}' => $transfer->toCustomer?->name ?? '--',
            '{{project_name}}' => $this->transferSubjectLabel($transfer),
            '{{company_name}}' => $companyName,
        ];

        $subject = $this->applyReplacements($subject, $replacements);
        $bodyHtml = $this->formatEmailBody($body, $replacements);

        foreach ($recipients as $email) {
            $this->sendGeneric($email, $subject, $bodyHtml, $fromEmail, $companyName, [], MailCategory::SYSTEM);
        }
    }

    public function sendTransferRejected(\App\Models\OwnershipTransfer $transfer): void
    {
        $transfer->loadMissing(['project', 'subscription.plan.product', 'toCustomer', 'fromCustomer.users']);

        $recipients = $transfer->fromCustomer?->users
            ?->where('role', \App\Enums\Role::CLIENT)
            ->pluck('email')
            ->filter();

        if (! $recipients || $recipients->isEmpty()) {
            return;
        }

        $template = EmailTemplate::query()->where('key', 'ownership_transfer_rejected')->first();
        $companyName = Setting::getValue('company_name', config('app.name'));
        $fromEmail = $this->resolveFromEmail($template);

        $subject = $template?->subject ?: "Your transfer request was declined - {$companyName}";
        $body = $template?->body ?: "Hi,\n\n{{to_customer_name}} declined your transfer of \"{{project_name}}\".\n\n{{company_name}}";

        $replacements = [
            '{{to_customer_name}}' => $transfer->toCustomer?->name ?? '--',
            '{{project_name}}' => $this->transferSubjectLabel($transfer),
            '{{company_name}}' => $companyName,
        ];

        $subject = $this->applyReplacements($subject, $replacements);
        $bodyHtml = $this->formatEmailBody($body, $replacements);

        foreach ($recipients as $email) {
            $this->sendGeneric($email, $subject, $bodyHtml, $fromEmail, $companyName, [], MailCategory::SYSTEM);
        }
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
        $bodyHtml = $this->formatEmailBody($body, $replacements);
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

    public function sendInvoiceReminder(Invoice $invoice, string $templateKey): void
    {
        $invoice->loadMissing(['customer']);

        $recipient = $invoice->customer?->email;
        if (! $recipient) {
            return;
        }

        $template = EmailTemplate::query()
            ->where('key', $templateKey)
            ->first();

        $companyName = Setting::getValue('company_name', config('app.name'));
        $invoiceNumber = is_numeric($invoice->number) ? $invoice->number : $invoice->id;
        $dateFormat = Setting::getValue('date_format', config('app.date_format', 'd-m-Y'));
        $dueDate = $invoice->due_date?->format($dateFormat) ?? '--';
        $paymentUrl = route('client.invoices.pay', $invoice);
        $fromEmail = $this->resolveFromEmail($template);

        $subject = $template?->subject ?: "Reminder: invoice {$invoiceNumber}";
        $body = $template?->body ?: "Hi {{client_name}},\n\nThis is a reminder that invoice {{invoice_number}} is due on {{invoice_due_date}}.\nTotal: {{invoice_total}}\n\nPay here: {{payment_url}}";

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
        $bodyHtml = $this->formatEmailBody($body, $replacements);
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
        $bodyHtml = $this->formatEmailBody($body, $replacements);

        $this->sendGeneric($customer->email, $subject, $bodyHtml, $fromEmail, $companyName, [], MailCategory::BILLING);
    }

    public function sendInvoicePaymentConfirmation(Invoice $invoice, ?string $reference = null): void
    {
        $this->sendInvoicePaymentStatusNotification($invoice, 'paid', $reference);
    }

    public function sendInvoicePaymentStatusNotification(Invoice $invoice, string $paymentEvent, ?string $reference = null): void
    {
        $invoice->loadMissing(['customer']);
        $customer = $invoice->customer;

        if (! $customer || ! $customer->email) {
            return;
        }

        $invoiceStatus = strtolower((string) ($invoice->status ?? 'unpaid'));
        $paymentEvent = strtolower(trim($paymentEvent));
        if ($paymentEvent === '') {
            $paymentEvent = 'updated';
        }

        $templateKey = match ($invoiceStatus) {
            'paid' => 'client_invoice_payment_confirmation',
            'unpaid' => 'invoice_created',
            default => 'client_invoice_payment_status_notification',
        };

        $template = EmailTemplate::query()->where('key', $templateKey)->first();
        $companyName = Setting::getValue('company_name', config('app.name'));
        $dateFormat = Setting::getValue('date_format', config('app.date_format', 'd-m-Y'));
        $invoiceNumber = is_numeric($invoice->number) ? $invoice->number : $invoice->id;
        $invoiceStatusLabel = $this->humanizeLabel($invoiceStatus);
        $paymentEventLabel = $this->humanizeLabel($paymentEvent);
        $paymentUrl = route('client.invoices.show', $invoice);
        $invoicePayUrl = route('client.invoices.pay', $invoice);
        $fromEmail = $this->resolveFromEmail($template);

        $subject = (string) ($template?->subject ?: match ($invoiceStatus) {
            'paid' => "Payment received for invoice {$invoiceNumber}",
            'unpaid' => "Invoice {$invoiceNumber} is unpaid",
            default => "Invoice {$invoiceNumber} payment update ({$invoiceStatusLabel})",
        });
        $body = (string) ($template?->body ?: "Hi {{client_name}},\n\n"
            ."This is a payment update for invoice {{invoice_number}}.\n"
            ."Invoice status: {{invoice_status}}\n"
            ."Payment event: {{payment_event}}\n"
            ."Total: {{invoice_total}}\n"
            ."Due date: {{invoice_due_date}}\n"
            ."Reference: {{payment_reference}}\n\n"
            ."View invoice: {{payment_url}}\n"
            ."Pay now: {{invoice_pay_url}}\n\n"
            ."Regards,\n{{company_name}}");

        $replacements = [
            '{{client_name}}' => $customer->name,
            '{{client_email}}' => $customer->email ?? '--',
            '{{invoice_number}}' => $invoiceNumber,
            '{{invoice_total}}' => $invoice->currency.' '.$invoice->total,
            '{{invoice_due_date}}' => $invoice->due_date?->format($dateFormat) ?? '--',
            '{{invoice_status}}' => $invoiceStatusLabel,
            '{{payment_event}}' => $paymentEventLabel,
            '{{payment_reference}}' => $reference ?? '--',
            '{{payment_url}}' => $paymentUrl,
            '{{invoice_pay_url}}' => $invoicePayUrl,
            '{{company_name}}' => $companyName,
        ];

        $subject = $this->applyReplacements($subject, $replacements);
        $bodyHtml = $this->formatEmailBody($body, $replacements);
        $attachments = [];

        if ($this->shouldAttachInvoiceForStatus($invoiceStatus)) {
            $attachment = $this->invoiceAttachment($invoice);
            if ($attachment) {
                $attachments[] = $attachment;
            }
        }

        $this->sendGeneric($customer->email, $subject, $bodyHtml, $fromEmail, $companyName, $attachments, MailCategory::BILLING);
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
        $bodyHtml = $this->formatEmailBody($body, $replacements);
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
        $bodyHtml = $this->formatEmailBody($body, $replacements);
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

    private function transferSubjectLabel(\App\Models\OwnershipTransfer $transfer): string
    {
        return $transfer->project?->name
            ?? $transfer->subscription?->plan?->product?->name
            ?? ('Subscription #'.$transfer->subscription_id);
    }

    private function applyReplacements(string $text, array $replacements): string
    {
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    private function formatEmailBody(string $body, array $replacements = []): string
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return '';
        }

        // Detect HTML-ness on the raw template (before substitution), so a
        // replacement value can't itself flip this into the unescaped branch.
        $looksLikeHtml = Str::contains($trimmed, ['<p', '<br', '<div', '<table', '<a ', '<strong', '<em', '<ul', '<ol', '<li']);

        if ($looksLikeHtml) {
            // Escape each replacement value individually — the template markup stays
            // trusted/unescaped, but substituted data (names, notes, etc.) can't inject HTML.
            $escaped = array_map(static fn ($value) => e((string) $value), $replacements);

            return $this->applyReplacements($trimmed, $escaped);
        }

        return nl2br(e($this->applyReplacements($trimmed, $replacements)));
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

    private function shouldAttachInvoiceForStatus(string $status): bool
    {
        return in_array($status, ['unpaid', 'paid'], true);
    }

    private function humanizeLabel(string $value): string
    {
        return (string) Str::of($value)->replace(['_', '-'], ' ')->title();
    }
}
