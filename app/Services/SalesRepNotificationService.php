<?php

namespace App\Services;

use App\Enums\MailCategory;
use App\Models\CommissionPayout;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\SalesRepresentative;
use App\Models\Setting;
use App\Services\Mail\MailSender;
use App\Support\Branding;
use App\Support\UrlResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class SalesRepNotificationService
{
    public function __construct(
        private readonly MailSender $mailSender
    ) {
    }

    public function sendInvoicePaymentConfirmationToRelatedSalesReps(Invoice $invoice, ?string $reference = null): void
    {
        $this->sendInvoicePaymentStatusToRelatedSalesReps($invoice, 'paid', $reference);
    }

    public function sendInvoicePaymentStatusToRelatedSalesReps(Invoice $invoice, string $paymentEvent = 'paid', ?string $reference = null): void
    {
        $invoice->loadMissing([
            'customer.defaultSalesRep:id,name,email',
            'subscription.salesRep:id,name,email',
            'orders.salesRep:id,name,email',
            'project.salesRepresentatives:id,name,email',
            'maintenance.salesRepresentatives:id,name,email',
            'maintenance.project.salesRepresentatives:id,name,email',
        ]);

        $recipients = $this->resolveInvoiceSalesReps($invoice);
        if ($recipients->isEmpty()) {
            return;
        }

        $invoiceStatus = strtolower((string) ($invoice->status ?? 'unpaid'));
        $paymentEvent = strtolower(trim($paymentEvent));
        if ($paymentEvent === '') {
            $paymentEvent = 'updated';
        }

        $templateKey = $invoiceStatus === 'paid'
            ? 'sales_rep_invoice_payment_confirmation'
            : 'sales_rep_invoice_payment_status_notification';
        $template = EmailTemplate::query()->where('key', $templateKey)->first();

        $companyName = (string) Setting::getValue('company_name', config('app.name'));
        $dateFormat = (string) Setting::getValue('date_format', config('app.date_format', 'd-m-Y'));
        $invoiceNumber = is_numeric($invoice->number) ? (string) $invoice->number : (string) $invoice->id;
        $paidDate = $invoice->paid_at?->format($dateFormat) ?? now()->format($dateFormat);
        $invoiceUrl = route('admin.invoices.show', $invoice);
        $attachment = $this->shouldAttachInvoiceForStatus($invoiceStatus) ? $this->invoiceAttachment($invoice) : null;
        $statusLabel = $this->humanizeLabel($invoiceStatus);
        $eventLabel = $this->humanizeLabel($paymentEvent);
        $subjectFallback = $invoiceStatus === 'paid'
            ? 'Payment received for invoice {{invoice_number}}'
            : 'Invoice {{invoice_number}} payment update ({{invoice_status}})';
        $bodyFallback = "Hello {{sales_rep_name}},\n\nInvoice {{invoice_number}} has a payment update.\nClient: {{client_name}}\nAmount: {{invoice_total}}\nInvoice status: {{invoice_status}}\nPayment event: {{payment_event}}\nPaid date: {{paid_date}}\nPayment reference: {{payment_reference}}\nInvoice link: {{invoice_url}}\n\nRegards,\n{{company_name}}";

        foreach ($recipients as $rep) {
            $replacements = [
                '{{sales_rep_name}}' => (string) ($rep->name ?? 'Sales Representative'),
                '{{invoice_number}}' => $invoiceNumber,
                '{{client_name}}' => (string) ($invoice->customer?->name ?? '--'),
                '{{invoice_total}}' => (string) ($invoice->currency.' '.number_format((float) $invoice->total, 2)),
                '{{invoice_status}}' => $statusLabel,
                '{{payment_event}}' => $eventLabel,
                '{{paid_date}}' => $paidDate,
                '{{payment_reference}}' => (string) ($reference ?: '--'),
                '{{invoice_url}}' => $invoiceUrl,
                '{{company_name}}' => $companyName,
            ];

            $subject = $this->applyReplacements((string) ($template?->subject ?: $subjectFallback), $replacements);
            $bodyHtml = $this->formatEmailBody(
                $this->applyReplacements((string) ($template?->body ?: $bodyFallback), $replacements)
            );

            $this->sendGeneric(
                (string) $rep->email,
                $subject,
                $bodyHtml,
                $companyName,
                $attachment ? [$attachment] : []
            );
        }
    }

    public function sendCommissionPayoutNotification(CommissionPayout $payout, string $event = 'created'): void
    {
        $event = in_array($event, ['created', 'paid', 'reversed'], true) ? $event : 'created';
        $payout->loadMissing([
            'salesRep:id,name,email',
            'project:id,name',
            'earnings:id,commission_payout_id,invoice_id',
            'earnings.invoice:id,number',
        ]);

        $salesRep = $payout->salesRep;
        if (! $salesRep || ! $salesRep->email) {
            return;
        }

        $templateKeyMap = [
            'created' => 'sales_rep_commission_payout_created',
            'paid' => 'sales_rep_commission_payout_paid',
            'reversed' => 'sales_rep_commission_payout_reversed',
        ];
        $subjectFallbackMap = [
            'created' => 'Commission payout created ({{payout_type}}) - #{{payout_id}}',
            'paid' => 'Commission payout paid ({{payout_type}}) - #{{payout_id}}',
            'reversed' => 'Commission payout reversed ({{payout_type}}) - #{{payout_id}}',
        ];
        $bodyFallbackMap = [
            'created' => "Hello {{sales_rep_name}},\n\nA commission payout has been created for you.\nPayout ID: {{payout_id}}\nType: {{payout_type}}\nAmount: {{payout_total}}\nStatus: {{payout_status}}\nSource: {{payout_source}}\nReference: {{payout_reference}}\nNote: {{payout_note}}\n\nRegards,\n{{company_name}}",
            'paid' => "Hello {{sales_rep_name}},\n\nA commission payout has been marked as paid.\nPayout ID: {{payout_id}}\nType: {{payout_type}}\nAmount: {{payout_total}}\nStatus: {{payout_status}}\nPaid date: {{payout_date}}\nSource: {{payout_source}}\nReference: {{payout_reference}}\nNote: {{payout_note}}\n\nRegards,\n{{company_name}}",
            'reversed' => "Hello {{sales_rep_name}},\n\nA commission payout has been reversed.\nPayout ID: {{payout_id}}\nType: {{payout_type}}\nAmount: {{payout_total}}\nStatus: {{payout_status}}\nSource: {{payout_source}}\nReference: {{payout_reference}}\nNote: {{payout_note}}\n\nRegards,\n{{company_name}}",
        ];

        $template = EmailTemplate::query()
            ->where('key', $templateKeyMap[$event])
            ->first();

        $companyName = (string) Setting::getValue('company_name', config('app.name'));
        $dateFormat = (string) Setting::getValue('date_format', config('app.date_format', 'd-m-Y'));
        $replacements = [
            '{{sales_rep_name}}' => (string) ($salesRep->name ?? 'Sales Representative'),
            '{{company_name}}' => $companyName,
            '{{payout_id}}' => (string) $payout->id,
            '{{payout_type}}' => ucfirst((string) ($payout->type ?? 'regular')),
            '{{payout_total}}' => (string) ((string) ($payout->currency ?: 'BDT').' '.number_format((float) $payout->total_amount, 2)),
            '{{payout_status}}' => ucfirst((string) ($payout->status ?? '--')),
            '{{payout_reference}}' => (string) ($payout->reference ?: '--'),
            '{{payout_note}}' => (string) ($payout->note ?: '--'),
            '{{payout_date}}' => $payout->paid_at?->format($dateFormat) ?? '--',
            '{{payout_source}}' => $this->resolvePayoutSourceLabel($payout),
        ];

        $subject = $this->applyReplacements(
            (string) ($template?->subject ?: $subjectFallbackMap[$event]),
            $replacements
        );
        $bodyHtml = $this->formatEmailBody(
            $this->applyReplacements((string) ($template?->body ?: $bodyFallbackMap[$event]), $replacements)
        );

        $this->sendGeneric((string) $salesRep->email, $subject, $bodyHtml, $companyName);
    }

    private function resolveInvoiceSalesReps(Invoice $invoice): Collection
    {
        $salesReps = collect();

        if ($invoice->customer?->defaultSalesRep instanceof SalesRepresentative) {
            $salesReps->push($invoice->customer->defaultSalesRep);
        }

        if ($invoice->subscription?->salesRep instanceof SalesRepresentative) {
            $salesReps->push($invoice->subscription->salesRep);
        }

        foreach ($invoice->orders ?? [] as $order) {
            if ($order->salesRep instanceof SalesRepresentative) {
                $salesReps->push($order->salesRep);
            }
        }

        foreach ($invoice->project?->salesRepresentatives ?? [] as $projectRep) {
            if ($projectRep instanceof SalesRepresentative) {
                $salesReps->push($projectRep);
            }
        }

        foreach ($invoice->maintenance?->salesRepresentatives ?? [] as $maintenanceRep) {
            if ($maintenanceRep instanceof SalesRepresentative) {
                $salesReps->push($maintenanceRep);
            }
        }

        foreach ($invoice->maintenance?->project?->salesRepresentatives ?? [] as $projectRep) {
            if ($projectRep instanceof SalesRepresentative) {
                $salesReps->push($projectRep);
            }
        }

        return $salesReps
            ->filter(fn ($rep) => (bool) trim((string) $rep->email))
            ->unique(function (SalesRepresentative $rep) {
                $email = strtolower(trim((string) $rep->email));

                return $email !== '' ? $email : 'rep-'.$rep->id;
            })
            ->values();
    }

    private function resolvePayoutSourceLabel(CommissionPayout $payout): string
    {
        if ($payout->project?->name) {
            return 'Project: '.$payout->project->name;
        }

        $invoice = $payout->earnings
            ->map(fn ($earning) => $earning->invoice)
            ->first(fn ($item) => $item !== null);

        if ($invoice) {
            $invoiceNumber = is_numeric($invoice->number) ? (string) $invoice->number : (string) $invoice->id;

            return 'Invoice: #'.$invoiceNumber;
        }

        if ((string) $payout->type === 'advance') {
            return 'Advance payment';
        }

        return 'Commission earnings';
    }

    private function sendGeneric(
        string $to,
        string $subject,
        string $bodyHtml,
        string $companyName,
        array $attachments = []
    ): void {
        try {
            $this->mailSender->sendView(
                MailCategory::BILLING,
                $to,
                'emails.generic',
                [
                    'subject' => $subject,
                    'companyName' => $companyName,
                    'logoUrl' => Branding::url(Setting::getValue('company_logo_path')),
                    'portalUrl' => UrlResolver::portalUrl(),
                    'portalLoginUrl' => UrlResolver::portalUrl().'/sales/login',
                    'portalLoginLabel' => 'log in to the sales area',
                    'bodyHtml' => new HtmlString($bodyHtml),
                ],
                $subject,
                $attachments
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to send sales representative notification.', [
                'to' => $to,
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

    private function invoiceAttachment(Invoice $invoice): ?array
    {
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
