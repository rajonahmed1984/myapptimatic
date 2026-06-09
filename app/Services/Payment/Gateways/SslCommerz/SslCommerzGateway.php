<?php

namespace App\Services\Payment\Gateways\SslCommerz;

use App\Models\PaymentAttempt;
use App\Services\Payment\Gateways\GatewayDriverInterface;
use App\Support\SystemLogger;
use Illuminate\Support\Facades\Http;

class SslCommerzGateway implements GatewayDriverInterface
{
    public function start(PaymentAttempt $attempt): array
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
        [$amount, $currency] = app(\App\Services\PaymentService::class)->resolveGatewayAmount($attempt, $settings);
        $payload = [
            'store_id' => $storeId,
            'store_passwd' => $storePassword,
            'total_amount' => number_format($amount, 2, '.', ''),
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

    public function confirm(PaymentAttempt $attempt, array $payload): bool
    {
        $gateway = $attempt->paymentGateway;
        $settings = $gateway->settings ?? [];
        $storeId = $settings['store_id'] ?? null;
        $storePassword = $settings['store_password'] ?? null;

        if (! $storeId || ! $storePassword) {
            app(\App\Services\PaymentService::class)->markFailed($attempt, 'SSLCommerz credentials are missing.');

            return false;
        }

        $valId = $payload['val_id'] ?? null;
        $status = strtolower((string) ($payload['status'] ?? ''));

        if (! $valId || $status === 'failed') {
            app(\App\Services\PaymentService::class)->markFailed($attempt, 'SSLCommerz payment failed.', [
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
            app(\App\Services\PaymentService::class)->markFailed($attempt, 'SSLCommerz validation failed.', [
                'response' => $validationResponse->json(),
            ]);

            return false;
        }

        $validation = $validationResponse->json();
        $validationStatus = strtolower((string) ($validation['status'] ?? ''));

        if (! in_array($validationStatus, ['valid', 'validated'], true)) {
            app(\App\Services\PaymentService::class)->markFailed($attempt, 'SSLCommerz returned an invalid status.', [
                'response' => $validation,
            ]);

            return false;
        }

        $reference = $validation['tran_id'] ?? $attempt->gateway_reference ?? $attempt->id;

        app(\App\Services\PaymentService::class)->markPaid($attempt, $reference, [
            'payload' => $payload,
            'response' => $validation,
        ]);

        SystemLogger::write('module', 'SSLCommerz payment validated.', [
            'payment_attempt_id' => $attempt->id,
            'invoice_id' => $attempt->invoice_id,
            'gateway' => $attempt->paymentGateway?->name,
            'driver' => $attempt->paymentGateway?->driver,
            'reference' => $reference,
            'payload' => $payload,
            'validation' => $validationStatus,
        ]);

        return true;
    }

    public function refund(PaymentAttempt $attempt, float $amount, string $reason): array
    {
        return [
            'status' => 'error',
            'message' => 'SSLCommerz refund not implemented.',
        ];
    }

    private function sslcommerzBaseUrl(array $settings): string
    {
        return ! empty($settings['sandbox'])
            ? 'https://sandbox.sslcommerz.com'
            : 'https://securepay.sslcommerz.com';
    }
}
