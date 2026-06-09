<?php

namespace App\Services\Payment\Gateways\Bkash;

use App\Models\PaymentAttempt;
use App\Services\Payment\Gateways\GatewayDriverInterface;
use App\Support\SystemLogger;
use Illuminate\Support\Facades\Http;

class BkashGateway implements GatewayDriverInterface
{
    public function start(PaymentAttempt $attempt): array
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
        [$amount, $currency] = app(\App\Services\PaymentService::class)->resolveGatewayAmount($attempt, $settings);

        $payload = [
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $currency,
            'merchantInvoiceNumber' => $attempt->gateway_reference,
            'intent' => 'sale',
            'callbackURL' => route('payments.bkash.callback', $attempt),
        ];

        $response = Http::timeout(30)->withHeaders([
            'Authorization' => $token,
            'X-APP-Key' => $settings['app_key'] ?? null,
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

    public function confirm(PaymentAttempt $attempt, array $payload): bool
    {
        $gateway = $attempt->paymentGateway;
        $settings = $gateway->settings ?? [];

        $username = $settings['username'] ?? null;
        $password = $settings['password'] ?? null;
        $appKey = $settings['app_key'] ?? null;
        $appSecret = $settings['app_secret'] ?? null;

        if (! $username || ! $password || ! $appKey || ! $appSecret) {
            app(\App\Services\PaymentService::class)->markFailed($attempt, 'bKash credentials are missing.');

            return false;
        }

        $paymentId = $payload['paymentID'] ?? $payload['paymentId'] ?? $attempt->external_id;
        $status = strtolower((string) ($payload['status'] ?? ''));

        if ($status === 'cancel') {
            app(\App\Services\PaymentService::class)->markCancelled($attempt, 'bKash payment cancelled by user.', [
                'payload' => $payload,
            ]);

            return false;
        }

        if (! $paymentId || $status === 'failure') {
            app(\App\Services\PaymentService::class)->markFailed($attempt, 'bKash payment failed.', [
                'payload' => $payload,
            ]);

            return false;
        }

        $token = $attempt->payload['token'] ?? null;
        if (! $token) {
            $token = $this->bkashToken($settings);
        }

        if (! $token) {
            app(\App\Services\PaymentService::class)->markFailed($attempt, 'Unable to authenticate with bKash.');

            return false;
        }

        $baseUrl = $this->bkashBaseUrl($settings);
        $executeResponse = null;
        $response = null;
        $timeoutOrFailed = false;

        try {
            $executeResponse = Http::timeout(30)->withHeaders([
                'Authorization' => $token,
                'X-APP-Key' => $appKey,
            ])->post($baseUrl.'/payment/execute', [
                'paymentID' => $paymentId,
            ]);

            if ($executeResponse && $executeResponse->successful()) {
                $response = $executeResponse->json();
            } else {
                $timeoutOrFailed = true;
            }
        } catch (\Throwable $e) {
            SystemLogger::write('module', 'bKash execute exception, falling back to query payment API.', [
                'payment_attempt_id' => $attempt->id,
                'error' => $e->getMessage(),
            ], level: 'warning');
            $timeoutOrFailed = true;
        }

        if ($timeoutOrFailed) {
            try {
                $queryResponse = Http::timeout(30)->withHeaders([
                    'Authorization' => $token,
                    'X-APP-Key' => $appKey,
                ])->post($baseUrl.'/payment/status', [
                    'paymentID' => $paymentId,
                ]);

                if ($queryResponse && $queryResponse->successful()) {
                    $response = $queryResponse->json();
                } else {
                    app(\App\Services\PaymentService::class)->markFailed($attempt, 'bKash execute failed and query payment fallback failed.', [
                        'execute_response' => $executeResponse?->json(),
                        'query_response' => $queryResponse?->json(),
                    ]);
                    return false;
                }
            } catch (\Throwable $qe) {
                app(\App\Services\PaymentService::class)->markFailed($attempt, 'bKash execute failed/timeout and query payment fallback threw exception.', [
                    'execute_error' => isset($e) ? $e->getMessage() : 'unknown',
                    'query_error' => $qe->getMessage(),
                ]);
                return false;
            }
        }

        $transactionStatus = strtolower((string) ($response['transactionStatus'] ?? ''));
        $statusCode = (string) ($response['statusCode'] ?? '');

        if ($transactionStatus && $transactionStatus !== 'completed' && $statusCode !== '0000') {
            app(\App\Services\PaymentService::class)->markFailed($attempt, 'bKash returned an invalid status.', [
                'response' => $response,
            ]);

            return false;
        }

        $reference = $response['trxId'] ?? $response['trxID'] ?? $paymentId;

        app(\App\Services\PaymentService::class)->markPaid($attempt, $reference, [
            'payload' => $payload,
            'response' => $response,
        ]);

        SystemLogger::write('module', 'bKash payment executed.', [
            'payment_attempt_id' => $attempt->id,
            'invoice_id' => $attempt->invoice_id,
            'gateway' => $attempt->paymentGateway?->name,
            'driver' => $attempt->paymentGateway?->driver,
            'reference' => $reference,
            'payload' => $payload,
            'response_status' => $transactionStatus ?: $statusCode,
        ]);

        return true;
    }

    public function refund(PaymentAttempt $attempt, float $amount, string $reason): array
    {
        return [
            'status' => 'error',
            'message' => 'bKash V1.2 refund not implemented.',
        ];
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

        $response = Http::timeout(30)
            ->withBasicAuth($username, $password)
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
}
