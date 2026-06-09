<?php

namespace App\Services\Payment\Gateways\PayPal;

use App\Models\PaymentAttempt;
use App\Services\Payment\Gateways\GatewayDriverInterface;
use Illuminate\Support\Facades\Http;

class PayPalGateway implements GatewayDriverInterface
{
    public function start(PaymentAttempt $attempt): array
    {
        $gateway = $attempt->paymentGateway;
        $settings = $gateway->settings ?? [];
        $clientId = $settings['client_id'] ?? null;
        $clientSecret = $settings['client_secret'] ?? null;
        [$amount, $currency] = app(\App\Services\PaymentService::class)->resolveGatewayAmount($attempt, $settings);

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
                    'value' => number_format($amount, 2, '.', ''),
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

    public function confirm(PaymentAttempt $attempt, array $payload): bool
    {
        $gateway = $attempt->paymentGateway;
        $settings = $gateway->settings ?? [];
        $clientId = $settings['client_id'] ?? null;
        $clientSecret = $settings['client_secret'] ?? null;

        if (! $clientId || ! $clientSecret) {
            app(\App\Services\PaymentService::class)->markFailed($attempt, 'PayPal Client ID/Secret are missing.');

            return false;
        }

        $orderId = $payload['order_id'] ?? $attempt->external_id;

        if (! $orderId) {
            app(\App\Services\PaymentService::class)->markFailed($attempt, 'PayPal order ID missing.');
            return false;
        }

        $baseUrl = $this->paypalBaseUrl($settings);

        $tokenResponse = Http::asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->post($baseUrl.'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if (! $tokenResponse->successful()) {
            app(\App\Services\PaymentService::class)->markFailed($attempt, 'PayPal authentication failed.', [
                'response' => $tokenResponse->json(),
            ]);

            return false;
        }

        $token = $tokenResponse->json('access_token');

        $captureResponse = Http::withToken($token)
            ->post($baseUrl.'/v2/checkout/orders/'.$orderId.'/capture');

        if (! $captureResponse->successful()) {
            app(\App\Services\PaymentService::class)->markFailed($attempt, 'PayPal capture failed.', [
                'response' => $captureResponse->json(),
            ]);

            return false;
        }

        app(\App\Services\PaymentService::class)->markPaid($attempt, $orderId, [
            'response' => $captureResponse->json(),
        ]);

        return true;
    }

    public function refund(PaymentAttempt $attempt, float $amount, string $reason): array
    {
        return [
            'status' => 'error',
            'message' => 'PayPal refund not implemented.',
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

        [$amount, $currency] = app(\App\Services\PaymentService::class)->resolveGatewayAmount($attempt, $settings);
        $amount = number_format($amount, 2, '.', '');
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
}
