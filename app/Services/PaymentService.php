<?php

namespace App\Services;

use App\Models\AccountingEntry;
use App\Models\Invoice;
use App\Models\PaymentAttempt;
use App\Models\PaymentGateway;
use App\Support\SystemLogger;
use App\Models\StatusAuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class PaymentService
{
    public function createAttempt(Invoice $invoice, PaymentGateway $gateway): PaymentAttempt
    {
        $attempt = PaymentAttempt::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'payment_gateway_id' => $gateway->id,
            'status' => 'pending',
            'amount' => (float) $invoice->total,
            'currency' => strtoupper($invoice->currency),
            'gateway_reference' => null,
        ]);

        $attempt->update([
            'gateway_reference' => sprintf('%s-%06d', $invoice->number, $attempt->id),
        ]);

        return $attempt;
    }

    public function initiate(PaymentAttempt $attempt): array
    {
        $gateway = $attempt->paymentGateway;

        SystemLogger::write('module', 'Payment initiation started via gateway.', [
            'payment_attempt_id' => $attempt->id,
            'invoice_id' => $attempt->invoice_id,
            'gateway' => $gateway?->name,
            'driver' => $gateway?->driver,
            'amount' => $attempt->amount,
            'currency' => $attempt->currency,
        ]);

        return match ($gateway->driver) {
            'paypal' => $this->startPayPal($attempt),
            'sslcommerz' => $this->startSslcommerz($attempt),
            'bkash' => $this->startBkash($attempt),
            'bkash_api' => $this->startBkashApi($attempt),
            default => [
                'status' => 'manual',
                'message' => 'Manual payment instructions provided.',
            ],
        };
    }

    public function markPaid(PaymentAttempt $attempt, string $reference, array $meta = []): void
    {
        if ($attempt->status === 'paid') {
            return;
        }

        $attempt->update([
            'status' => 'paid',
            'processed_at' => Carbon::now(),
            'response' => $this->mergeMeta($attempt->response, $meta),
        ]);

        SystemLogger::write('module', 'Payment successful via gateway.', [
            'payment_attempt_id' => $attempt->id,
            'invoice_id' => $attempt->invoice_id,
            'gateway' => $attempt->paymentGateway?->name,
            'driver' => $attempt->paymentGateway?->driver,
            'amount' => $attempt->amount,
            'currency' => $attempt->currency,
            'reference' => $reference,
        ]);

        AccountingEntry::create([
            'entry_date' => Carbon::today(),
            'type' => 'payment',
            'amount' => $attempt->amount,
            'currency' => $attempt->currency,
            'description' => sprintf('Payment via %s', $attempt->paymentGateway->name),
            'reference' => $reference,
            'customer_id' => $attempt->customer_id,
            'invoice_id' => $attempt->invoice_id,
            'payment_gateway_id' => $attempt->payment_gateway_id,
            'created_by' => null,
        ]);

        $invoice = $attempt->invoice;
        if ($invoice && $invoice->status !== 'paid') {
            $previousStatus = $invoice->status;
            $invoice->update([
                'status' => 'paid',
                'paid_at' => $invoice->paid_at ?? Carbon::now(),
            ]);

            StatusAuditLog::logChange(
                Invoice::class,
                $invoice->id,
                $previousStatus,
                'paid',
                'payment_received'
            );
            try {
                app(\App\Services\AdminNotificationService::class)->sendInvoicePaid($invoice);
            } catch (\Throwable) {
                // Notification errors should not break payment flow.
            }

            try {
                app(\App\Services\ClientNotificationService::class)->sendInvoicePaymentConfirmation($invoice, $reference);
            } catch (\Throwable) {
                // Client notification failures should not interfere with payment.
            }

            // Check if customer has any remaining unpaid/overdue invoices
            // If not, clear the billing block
            $customerId = $attempt->customer_id;
            $hasUnpaidInvoices = \App\Models\Invoice::query()
                ->where('customer_id', $customerId)
                ->whereIn('status', ['unpaid', 'overdue'])
                ->exists();

            if (! $hasUnpaidInvoices) {
                // Customer has no more unpaid invoices, restore access immediately
                \App\Models\Customer::query()
                    ->where('id', $customerId)
                    ->update(['access_override_until' => null]);
            }
        }

        SystemLogger::write('activity', 'Invoice marked as paid.', [
            'invoice_id' => $attempt->invoice_id,
            'payment_attempt_id' => $attempt->id,
            'gateway' => $attempt->paymentGateway?->driver,
            'reference' => $reference,
        ]);
    }

    public function markFailed(PaymentAttempt $attempt, string $message, array $meta = []): void
    {
        $attempt->update([
            'status' => 'failed',
            'processed_at' => Carbon::now(),
            'response' => $this->mergeMeta($attempt->response, array_merge($meta, [
                'message' => $message,
            ])),
        ]);

        SystemLogger::write('module', 'Payment failed.', [
            'payment_attempt_id' => $attempt->id,
            'invoice_id' => $attempt->invoice_id,
            'gateway' => $attempt->paymentGateway?->driver,
            'message' => $message,
        ], level: 'error');
    }

    public function markCancelled(PaymentAttempt $attempt, string $message, array $meta = []): void
    {
        $attempt->update([
            'status' => 'cancelled',
            'processed_at' => Carbon::now(),
            'response' => $this->mergeMeta($attempt->response, array_merge($meta, [
                'message' => $message,
            ])),
        ]);

        SystemLogger::write('module', 'Payment cancelled via gateway.', [
            'payment_attempt_id' => $attempt->id,
            'invoice_id' => $attempt->invoice_id,
            'gateway' => $attempt->paymentGateway?->name,
            'driver' => $attempt->paymentGateway?->driver,
            'message' => $message,
        ]);
    }

    public function capturePayPal(PaymentAttempt $attempt, string $orderId): bool
    {
        $gateway = $attempt->paymentGateway;
        $settings = $gateway->settings ?? [];
        $clientId = $settings['client_id'] ?? null;
        $clientSecret = $settings['client_secret'] ?? null;

        if (! $clientId || ! $clientSecret) {
            $this->markFailed($attempt, 'PayPal Client ID/Secret are missing.');

            return false;
        }

        $baseUrl = $this->paypalBaseUrl($settings);

        $tokenResponse = Http::asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->post($baseUrl.'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if (! $tokenResponse->successful()) {
            $this->markFailed($attempt, 'PayPal authentication failed.', [
                'response' => $tokenResponse->json(),
            ]);

            return false;
        }

        $token = $tokenResponse->json('access_token');

        $captureResponse = Http::withToken($token)
            ->post($baseUrl.'/v2/checkout/orders/'.$orderId.'/capture');

        if (! $captureResponse->successful()) {
            $this->markFailed($attempt, 'PayPal capture failed.', [
                'response' => $captureResponse->json(),
            ]);

            return false;
        }

        $this->markPaid($attempt, $orderId, [
            'response' => $captureResponse->json(),
        ]);

        return true;
    }

    public function confirmSslcommerz(PaymentAttempt $attempt, array $payload): bool
    {
        $gateway = $attempt->paymentGateway;
        $settings = $gateway->settings ?? [];
        $storeId = $settings['store_id'] ?? null;
        $storePassword = $settings['store_password'] ?? null;

        if (! $storeId || ! $storePassword) {
            $this->markFailed($attempt, 'SSLCommerz credentials are missing.');

            return false;
        }

        $valId = $payload['val_id'] ?? null;
        $status = strtolower((string) ($payload['status'] ?? ''));

        if (! $valId || $status === 'failed') {
            $this->markFailed($attempt, 'SSLCommerz payment failed.', [
                'payload' => $payload,
            ]);

            return false;
        }

        $baseUrl = $this->sslcommerzBaseUrl($settings);

        $validationResponse = Http::get($baseUrl.'/validator/api/validationserverAPI.php', [
            'val_id' => $valId,
            'store_id' => $storeId,
            'store_passwd' => $storePassword,
            'format' => 'json',
        ]);

        if (! $validationResponse->successful()) {
            $this->markFailed($attempt, 'SSLCommerz validation failed.', [
                'response' => $validationResponse->json(),
            ]);

            return false;
        }

        $validation = $validationResponse->json();
        $validationStatus = strtolower((string) ($validation['status'] ?? ''));

        if (! in_array($validationStatus, ['valid', 'validated'], true)) {
            $this->markFailed($attempt, 'SSLCommerz returned an invalid status.', [
                'response' => $validation,
            ]);

            return false;
        }

        $reference = $validation['tran_id'] ?? $attempt->gateway_reference ?? $attempt->id;

        $this->markPaid($attempt, $reference, [
            'payload' => $payload,
            'response' => $validation,
        ]);

        return true;
    }

    public function confirmBkash(PaymentAttempt $attempt, array $payload): bool
    {
        $gateway = $attempt->paymentGateway;
        $settings = $gateway->settings ?? [];

        $username = $settings['username'] ?? null;
        $password = $settings['password'] ?? null;
        $appKey = $settings['app_key'] ?? null;
        $appSecret = $settings['app_secret'] ?? null;

        if (! $username || ! $password || ! $appKey || ! $appSecret) {
            $this->markFailed($attempt, 'bKash credentials are missing.');

            return false;
        }

        $paymentId = $payload['paymentID'] ?? $attempt->external_id;
        $status = strtolower((string) ($payload['status'] ?? ''));

        if (! $paymentId || $status === 'failure') {
            $this->markFailed($attempt, 'bKash payment failed.', [
                'payload' => $payload,
            ]);

            return false;
        }

        $token = $attempt->payload['token'] ?? null;
        if (! $token) {
            $token = $this->bkashToken($settings);
        }

        if (! $token) {
            $this->markFailed($attempt, 'Unable to authenticate with bKash.');

            return false;
        }

        $baseUrl = $this->bkashBaseUrl($settings);

        $executeResponse = Http::withHeaders([
            'Authorization' => $token,
            'X-APP-Key' => $appKey,
        ])->post($baseUrl.'/payment/execute', [
            'paymentID' => $paymentId,
        ]);

        if (! $executeResponse->successful()) {
            $this->markFailed($attempt, 'bKash execute failed.', [
                'response' => $executeResponse->json(),
            ]);

            return false;
        }

        $response = $executeResponse->json();
        $transactionStatus = strtolower((string) ($response['transactionStatus'] ?? ''));
        $statusCode = (string) ($response['statusCode'] ?? '');

        if ($transactionStatus && $transactionStatus !== 'completed' && $statusCode !== '0000') {
            $this->markFailed($attempt, 'bKash returned an invalid status.', [
                'response' => $response,
            ]);

            return false;
        }

        $reference = $response['trxID'] ?? $paymentId;

        $this->markPaid($attempt, $reference, [
            'payload' => $payload,
            'response' => $response,
        ]);

        return true;
    }

    private function startPayPal(PaymentAttempt $attempt): array
    {
        $gateway = $attempt->paymentGateway;
        $settings = $gateway->settings ?? [];
        $clientId = $settings['client_id'] ?? null;
        $clientSecret = $settings['client_secret'] ?? null;
        $currency = strtoupper((string) ($settings['processing_currency'] ?? $attempt->currency));

        if (! $clientId || ! $clientSecret) {
            $email = $settings['paypal_email'] ?? null;

            if ($email) {
                return ['status' => 'redirect', 'url' => $this->paypalClassicUrl($attempt, $settings, $email)];
            }

            return ['status' => 'manual', 'message' => 'PayPal email is missing.'];
        }

        $baseUrl = $this->paypalBaseUrl($settings);

        $tokenResponse = Http::asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->post($baseUrl.'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if (! $tokenResponse->successful()) {
            return ['status' => 'error', 'message' => 'PayPal authentication failed.'];
        }

        $token = $tokenResponse->json('access_token');
        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $attempt->gateway_reference,
                'description' => 'Invoice '.$attempt->invoice->number,
                'amount' => [
                    'currency_code' => $currency,
                    'value' => number_format((float) $attempt->amount, 2, '.', ''),
                ],
            ]],
            'application_context' => [
                'return_url' => route('payments.paypal.return', $attempt),
                'cancel_url' => route('payments.paypal.cancel', $attempt),
            ],
        ];

        $orderResponse = Http::withToken($token)
            ->post($baseUrl.'/v2/checkout/orders', $payload);

        if (! $orderResponse->successful()) {
            return ['status' => 'error', 'message' => 'PayPal order creation failed.'];
        }

        $order = $orderResponse->json();
        $approveUrl = collect($order['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;

        if (! $approveUrl) {
            return ['status' => 'error', 'message' => 'PayPal approval URL not found.'];
        }

        $attempt->update([
            'external_id' => $order['id'] ?? null,
            'payload' => $payload,
            'response' => $order,
        ]);

        return ['status' => 'redirect', 'url' => $approveUrl];
    }

    private function startSslcommerz(PaymentAttempt $attempt): array
    {
        $gateway = $attempt->paymentGateway;
        $settings = $gateway->settings ?? [];
        $storeId = $settings['store_id'] ?? null;
        $storePassword = $settings['store_password'] ?? null;

        if (! $storeId || ! $storePassword) {
            return ['status' => 'error', 'message' => 'SSLCommerz credentials are missing.'];
        }

        $customer = $attempt->customer;
        $baseUrl = $this->sslcommerzBaseUrl($settings);
        $currency = strtoupper((string) ($settings['processing_currency'] ?? $attempt->currency));
        $payload = [
            'store_id' => $storeId,
            'store_passwd' => $storePassword,
            'total_amount' => number_format((float) $attempt->amount, 2, '.', ''),
            'currency' => $currency,
            'tran_id' => $attempt->gateway_reference,
            'success_url' => route('payments.sslcommerz.success', $attempt),
            'fail_url' => route('payments.sslcommerz.fail', $attempt),
            'cancel_url' => route('payments.sslcommerz.cancel', $attempt),
            'cus_name' => $customer?->name ?? 'Customer',
            'cus_email' => $customer?->email ?? 'client@example.com',
            'cus_add1' => $customer?->address ?? 'N/A',
            'cus_phone' => $customer?->phone ?? 'N/A',
            'product_name' => $attempt->invoice->number,
            'product_category' => 'Software',
            'shipping_method' => 'NO',
            'product_profile' => 'general',
        ];

        $response = Http::asForm()->post($baseUrl.'/gwprocess/v4/api.php', $payload);

        if (! $response->successful()) {
            return ['status' => 'error', 'message' => 'SSLCommerz request failed.'];
        }

        $data = $response->json();
        $gatewayUrl = $data['GatewayPageURL'] ?? null;

        if (! $gatewayUrl) {
            return ['status' => 'error', 'message' => 'SSLCommerz gateway URL not found.'];
        }

        $attempt->update([
            'payload' => $payload,
            'response' => $data,
            'external_id' => $data['sessionkey'] ?? null,
        ]);

        return ['status' => 'redirect', 'url' => $gatewayUrl];
    }

    private function startBkash(PaymentAttempt $attempt): array
    {
        $gateway = $attempt->paymentGateway;
        $settings = $gateway->settings ?? [];
        $paymentUrl = $settings['payment_url'] ?? null;

        if ($paymentUrl) {
            return ['status' => 'redirect', 'url' => $paymentUrl];
        }

        $token = $this->bkashToken($settings);

        if (! $token) {
            return ['status' => 'error', 'message' => 'bKash authentication failed.'];
        }

        $baseUrl = $this->bkashBaseUrl($settings);
        $appKey = $settings['app_key'] ?? null;

        $currency = strtoupper((string) ($settings['processing_currency'] ?? $attempt->currency));

        $payload = [
            'amount' => number_format((float) $attempt->amount, 2, '.', ''),
            'currency' => $currency,
            'merchantInvoiceNumber' => $attempt->gateway_reference,
            'intent' => 'sale',
            'callbackURL' => route('payments.bkash.callback', $attempt),
        ];

        $response = Http::withHeaders([
            'Authorization' => $token,
            'X-APP-Key' => $appKey,
        ])->post($baseUrl.'/payment/create', $payload);

        if (! $response->successful()) {
            return ['status' => 'error', 'message' => 'bKash payment creation failed.'];
        }

        $data = $response->json();
        $redirectUrl = $data['bkashURL'] ?? null;

        if (! $redirectUrl) {
            return ['status' => 'error', 'message' => 'bKash redirect URL not found.'];
        }

        $attempt->update([
            'payload' => array_merge($payload, ['token' => $token]),
            'response' => $data,
            'external_id' => $data['paymentID'] ?? null,
        ]);

        return ['status' => 'redirect', 'url' => $redirectUrl];
    }

    private function startBkashApi(PaymentAttempt $attempt): array
    {
        $settings = $attempt->paymentGateway->settings ?? [];

        if (empty($settings['api_key']) || empty($settings['merchant_short_code']) || empty($settings['service_id'])) {
            return ['status' => 'error', 'message' => 'bKash API credentials are missing.'];
        }

        return [
            'status' => 'manual',
            'message' => 'bKash API payments require manual confirmation.',
        ];
    }

    private function paypalBaseUrl(array $settings): string
    {
        return ! empty($settings['sandbox'])
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    private function paypalClassicUrl(PaymentAttempt $attempt, array $settings, string $email): string
    {
        $baseUrl = ! empty($settings['sandbox'])
            ? 'https://www.sandbox.paypal.com/cgi-bin/webscr'
            : 'https://www.paypal.com/cgi-bin/webscr';

        $currency = strtoupper((string) ($settings['processing_currency'] ?? $attempt->currency));
        $amount = number_format((float) $attempt->amount, 2, '.', '');
        $itemName = 'Invoice '.$attempt->invoice->number;

        $query = http_build_query([
            'cmd' => '_xclick',
            'business' => $email,
            'item_name' => $itemName,
            'amount' => $amount,
            'currency_code' => $currency,
            'custom' => (string) $attempt->id,
            'return' => route('payments.paypal.return', $attempt),
            'cancel_return' => route('payments.paypal.cancel', $attempt),
        ]);

        return $baseUrl.'?'.$query;
    }

    private function sslcommerzBaseUrl(array $settings): string
    {
        return ! empty($settings['sandbox'])
            ? 'https://sandbox.sslcommerz.com'
            : 'https://securepay.sslcommerz.com';
    }

    private function bkashBaseUrl(array $settings): string
    {
        return ! empty($settings['sandbox'])
            ? 'https://checkout.sandbox.bka.sh/v1.2.0-beta/checkout'
            : 'https://checkout.pay.bka.sh/v1.2.0-beta/checkout';
    }

    private function bkashToken(array $settings): ?string
    {
        $username = $settings['username'] ?? null;
        $password = $settings['password'] ?? null;
        $appKey = $settings['app_key'] ?? null;
        $appSecret = $settings['app_secret'] ?? null;

        if (! $username || ! $password || ! $appKey || ! $appSecret) {
            return null;
        }

        $baseUrl = $this->bkashBaseUrl($settings);

        $response = Http::withBasicAuth($username, $password)
            ->withHeaders(['X-APP-Key' => $appKey])
            ->post($baseUrl.'/token/grant', [
                'app_key' => $appKey,
                'app_secret' => $appSecret,
            ]);

        if (! $response->successful()) {
            return null;
        }

        return $response->json('id_token');
    }

    private function mergeMeta(?array $existing, array $meta): array
    {
        return array_merge($existing ?? [], $meta);
    }
}
