<?php

namespace App\Services\Sms;

use App\Models\Invoice;
use App\Models\Setting;
use App\Support\SystemLogger;
use Illuminate\Support\Facades\Http;

/**
 * Client SMS via the 24bulksmsbd.com gateway.
 *
 * The gateway takes customer_id, api_key, mobile_no and message, answers with
 * {"status": "...", "message": "..."} and only accepts requests coming from a
 * whitelisted server IP — so a local machine will always get "not whitelisted".
 */
class SmsService
{
    public const DEFAULT_INVOICE_CREATED_TEMPLATE = 'Dear {{client_name}}, invoice #{{invoice_number}} of {{invoice_total}} has been created. Due date: {{invoice_due_date}}. Pay: {{payment_url}} - {{company_name}}';

    public const DEFAULT_INVOICE_PAID_TEMPLATE = 'Dear {{client_name}}, we have received your payment of {{invoice_total}} for invoice #{{invoice_number}}. Thank you! - {{company_name}}';

    public function enabled(): bool
    {
        return (bool) (int) Setting::getValue('sms_enabled', config('sms.enabled') ? '1' : '0');
    }

    public function isConfigured(): bool
    {
        return $this->apiUrl() !== '' && $this->customerId() !== '' && $this->apiKey() !== '';
    }

    public function invoiceCreatedEnabled(): bool
    {
        return (bool) (int) Setting::getValue('sms_invoice_created_enabled', '1');
    }

    public function invoicePaidEnabled(): bool
    {
        return (bool) (int) Setting::getValue('sms_invoice_paid_enabled', '1');
    }

    public function invoiceCreatedTemplate(): string
    {
        $template = trim((string) Setting::getValue('sms_invoice_created_template', ''));

        return $template !== '' ? $template : self::DEFAULT_INVOICE_CREATED_TEMPLATE;
    }

    public function invoicePaidTemplate(): string
    {
        $template = trim((string) Setting::getValue('sms_invoice_paid_template', ''));

        return $template !== '' ? $template : self::DEFAULT_INVOICE_PAID_TEMPLATE;
    }

    public function sendInvoiceCreated(Invoice $invoice): void
    {
        if (! $this->enabled() || ! $this->invoiceCreatedEnabled()) {
            return;
        }

        if ((string) $invoice->status !== 'unpaid' || (float) $invoice->total <= 0) {
            return;
        }

        $this->sendForInvoice($invoice, $this->invoiceCreatedTemplate(), 'invoice_created');
    }

    public function sendInvoicePaid(Invoice $invoice): void
    {
        if (! $this->enabled() || ! $this->invoicePaidEnabled()) {
            return;
        }

        if ((string) $invoice->status !== 'paid') {
            return;
        }

        $this->sendForInvoice($invoice, $this->invoicePaidTemplate(), 'invoice_paid');
    }

    /**
     * @return array{success: bool, message: string, mobile: string|null}
     */
    public function send(string $mobile, string $message): array
    {
        $normalized = $this->normalizeMobile($mobile);

        if ($normalized === null) {
            return ['success' => false, 'message' => 'Invalid mobile number.', 'mobile' => null];
        }

        $message = trim($message);
        if ($message === '') {
            return ['success' => false, 'message' => 'Message is empty.', 'mobile' => $normalized];
        }

        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'SMS gateway is not configured.', 'mobile' => $normalized];
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout(max(5, (int) config('sms.timeout', 15)))
                ->post($this->apiUrl(), [
                    'customer_id' => $this->customerId(),
                    'api_key' => $this->apiKey(),
                    'mobile_no' => $normalized,
                    'message' => $message,
                ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'SMS gateway request failed: '.$e->getMessage(), 'mobile' => $normalized];
        }

        $body = trim((string) $response->body());
        $json = $response->json();
        $status = is_array($json) ? strtolower(trim((string) ($json['status'] ?? ''))) : '';
        $gatewayMessage = is_array($json) ? trim((string) ($json['message'] ?? '')) : '';

        $success = $response->successful() && (
            $status !== ''
                ? ! str_contains($status, 'fail') && ! str_contains($status, 'error')
                : $body !== '' && ! str_contains(strtolower($body), 'fail')
        );

        return [
            'success' => $success,
            'message' => $gatewayMessage !== '' ? $gatewayMessage : ($body !== '' ? mb_substr($body, 0, 255) : 'HTTP '.$response->status()),
            'mobile' => $normalized,
        ];
    }

    /**
     * Bangladeshi mobile numbers in any common shape (01XXXXXXXXX,
     * +8801XXXXXXXXX, 8801XXXXXXXXX, 1XXXXXXXXX) become 8801XXXXXXXXX.
     * Anything else is rejected, since the gateway only delivers locally.
     */
    public function normalizeMobile(?string $mobile): ?string
    {
        foreach (preg_split('/[,;\/]+/', (string) $mobile) ?: [] as $candidate) {
            $digits = preg_replace('/\D+/', '', $candidate) ?? '';

            if (str_starts_with($digits, '00')) {
                $digits = substr($digits, 2);
            }

            if (preg_match('/^8801[3-9]\d{8}$/', $digits)) {
                return $digits;
            }

            if (preg_match('/^01[3-9]\d{8}$/', $digits)) {
                return '88'.$digits;
            }

            if (preg_match('/^1[3-9]\d{8}$/', $digits)) {
                return '880'.$digits;
            }
        }

        return null;
    }

    private function sendForInvoice(Invoice $invoice, string $template, string $event): void
    {
        $invoice->loadMissing('customer');
        $customer = $invoice->customer;
        $context = [
            'event' => $event,
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
        ];

        if (! $this->isConfigured()) {
            SystemLogger::write('sms', 'Invoice SMS skipped: gateway not configured.', $context, level: 'warning');

            return;
        }

        if (! $customer || $this->normalizeMobile($customer->phone) === null) {
            SystemLogger::write('sms', 'Invoice SMS skipped: customer has no valid mobile number.', $context + [
                'phone' => $customer?->phone,
            ], level: 'warning');

            return;
        }

        $result = $this->send((string) $customer->phone, $this->renderInvoiceMessage($invoice, $template));

        SystemLogger::write(
            'sms',
            $result['success'] ? 'Invoice SMS sent.' : 'Invoice SMS failed.',
            $context + [
                'mobile' => $result['mobile'],
                'gateway_message' => $result['message'],
            ],
            level: $result['success'] ? 'info' : 'error'
        );
    }

    private function renderInvoiceMessage(Invoice $invoice, string $template): string
    {
        $dateFormat = Setting::getValue('date_format', config('app.date_format', 'd-m-Y'));
        $invoiceNumber = is_numeric($invoice->number) ? $invoice->number : $invoice->id;

        $replacements = [
            '{{client_name}}' => $invoice->customer?->name ?? 'Customer',
            '{{company_name}}' => (string) Setting::getValue('company_name', config('app.name')),
            '{{invoice_number}}' => (string) $invoiceNumber,
            '{{invoice_total}}' => trim($invoice->currency.' '.number_format((float) $invoice->total, 2)),
            '{{invoice_due_date}}' => $invoice->due_date?->format($dateFormat) ?? '--',
            '{{payment_url}}' => route('client.invoices.pay', $invoice),
            '{{invoice_url}}' => route('client.invoices.show', $invoice),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    private function apiUrl(): string
    {
        return trim((string) Setting::getValue('sms_api_url', config('sms.api_url')));
    }

    private function customerId(): string
    {
        return trim((string) Setting::getValue('sms_customer_id', config('sms.customer_id')));
    }

    private function apiKey(): string
    {
        return trim((string) Setting::getValue('sms_api_key', config('sms.api_key')));
    }
}
