<?php

namespace App\Services\Payment\Gateways\BkashApi;

use App\Models\PaymentAttempt;
use App\Services\Payment\Gateways\GatewayDriverInterface;
use App\Support\SystemLogger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BkashApiGateway implements GatewayDriverInterface
{
    public function start(PaymentAttempt $attempt): array
    {
        $gateway = $attempt->paymentGateway;
        $settings = $gateway->settings ?? [];

        $username = $settings['username'] ?? null;
        $password = $settings['password'] ?? null;
        $appKey = $settings['app_key'] ?? null;
        $appSecret = $settings['app_secret'] ?? null;

        if (! $username || ! $password || ! $appKey || ! $appSecret) {
            return ['status' => 'error', 'message' => 'bKash API credentials are missing.'];
        }

        $token = $this->bkashToken($settings);

        if (! $token) {
            return ['status' => 'error', 'message' => 'bKash API authentication failed.'];
        }

        $baseUrl = $this->bkashBaseUrl($settings);

        // Force BDT for bKash transactions
        $settings['processing_currency'] = 'BDT';
        [$amount, $currency] = app(\App\Services\PaymentService::class)->resolveGatewayAmount($attempt, $settings);

        $payerReference = preg_replace('/[^0-9]/', '', $attempt->customer?->phone ?? '');
        if (strlen($payerReference) < 11) {
            $payerReference = '01770618567'; // Fallback to a valid sandbox/default format wallet number
        }

        $payload = [
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => 'BDT',
            'intent' => 'sale',
            'payerReference' => $payerReference,
            'merchantInvoiceNumber' => $attempt->gateway_reference,
            'callbackURL' => route('payments.bkash.callback', $attempt),
        ];

        $response = Http::timeout(30)->withHeaders([
            'Authorization' => $token,
            'X-APP-Key' => $appKey,
        ])->post($baseUrl.'/tokenized-checkout/payment/create', $payload);

        Log::info('bKash V2 Create Payment API', [
            'action' => 'Create Payment',
            'request_url' => $baseUrl.'/tokenized-checkout/payment/create',
            'request_body' => $payload,
            'response_status' => $response->status(),
            'response_body' => $response->json(),
        ]);

        if (! $response->successful()) {
            SystemLogger::write('module', 'bKash V2 payment creation failed.', [
                'response' => $response->json(),
                'status' => $response->status(),
                'payload' => $payload,
            ], level: 'error');
            return ['status' => 'error', 'message' => 'bKash payment creation failed.'];
        }

        $data = $response->json();
        $redirectUrl = $data['bkashURL'] ?? null;

        if (! $redirectUrl) {
            SystemLogger::write('module', 'bKash V2 redirect URL missing.', [
                'response' => $data,
            ], level: 'error');
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
            ])->post($baseUrl.'/tokenized-checkout/payment/execute', [
                'paymentId' => $paymentId,
            ]);

            Log::info('bKash V2 Execute Payment API', [
                'action' => 'Execute Payment',
                'request_url' => $baseUrl.'/tokenized-checkout/payment/execute',
                'request_body' => [
                    'paymentId' => $paymentId,
                ],
                'response_status' => $executeResponse?->status(),
                'response_body' => $executeResponse?->json(),
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
            // Call Query Payment API as fallback
            try {
                $queryResponse = Http::timeout(30)->withHeaders([
                    'Authorization' => $token,
                    'X-APP-Key' => $appKey,
                ])->post($baseUrl.'/tokenized-checkout/query/payment', [
                    'paymentId' => $paymentId,
                ]);

                Log::info('bKash V2 Query Payment API', [
                    'action' => 'Query Payment',
                    'request_url' => $baseUrl.'/tokenized-checkout/query/payment',
                    'request_body' => [
                        'paymentId' => $paymentId,
                    ],
                    'response_status' => $queryResponse?->status(),
                    'response_body' => $queryResponse?->json(),
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
        $gateway = $attempt->paymentGateway;
        $settings = $gateway->settings ?? [];

        $username = $settings['username'] ?? null;
        $password = $settings['password'] ?? null;
        $appKey = $settings['app_key'] ?? null;
        $appSecret = $settings['app_secret'] ?? null;

        if (! $username || ! $password || ! $appKey || ! $appSecret) {
            return ['status' => 'error', 'message' => 'bKash API credentials are missing.'];
        }

        $token = $this->bkashToken($settings);
        if (! $token) {
            return ['status' => 'error', 'message' => 'bKash authentication failed.'];
        }

        $baseUrl = $this->bkashBaseUrl($settings);
        $paymentId = $attempt->external_id; // the paymentId returned during create
        $originalTrxId = $attempt->response['response']['trxId'] ?? $attempt->response['response']['trxID'] ?? $attempt->response['trxId'] ?? $attempt->response['trxID'] ?? null;

        if (! $paymentId) {
            return ['status' => 'error', 'message' => 'Original payment ID is missing.'];
        }

        if (! $originalTrxId) {
            return ['status' => 'error', 'message' => 'Original transaction ID (trxId) is missing.'];
        }

        $payload = [
            'paymentId' => $paymentId,
            'refundAmount' => number_format($amount, 2, '.', ''),
            'sku' => 'Refund',
            'reason' => substr($reason, 0, 255) ?: 'Refund',
            'trxId' => $originalTrxId,
        ];

        $timeoutOrFailed = false;
        $response = null;

        try {
            $refundResponse = Http::timeout(30)->withHeaders([
                'Authorization' => $token,
                'X-APP-Key' => $appKey,
            ])->post($baseUrl.'/tokenized-checkout/refund/payment/transaction', $payload);

            if ($refundResponse->successful()) {
                $response = $refundResponse->json();
            } else {
                $timeoutOrFailed = true;
            }
        } catch (\Throwable $e) {
            SystemLogger::write('module', 'bKash refund exception, falling back to refund status API.', [
                'payment_attempt_id' => $attempt->id,
                'error' => $e->getMessage(),
            ], level: 'warning');
            $timeoutOrFailed = true;
        }

        if ($timeoutOrFailed) {
            // Call Refund Status API as a fallback
            try {
                $statusResponse = Http::timeout(30)->withHeaders([
                    'Authorization' => $token,
                    'X-APP-Key' => $appKey,
                ])->post($baseUrl.'/tokenized-checkout/refund/payment/status', [
                    'paymentId' => $paymentId,
                    'trxId' => $originalTrxId,
                ]);

                if ($statusResponse->successful()) {
                    $response = $statusResponse->json();
                } else {
                    $errorMessage = (isset($refundResponse) && $refundResponse->json('errorMessageEn')) 
                        ? $refundResponse->json('errorMessageEn') 
                        : ($statusResponse->json('errorMessageEn') ?? 'Refund request failed.');
                    return ['status' => 'error', 'message' => $errorMessage];
                }
            } catch (\Throwable $qe) {
                return ['status' => 'error', 'message' => 'Refund request failed due to connection error: ' . $qe->getMessage()];
            }
        }

        // Validate response
        $refundStatus = strtolower((string) ($response['refundTransactionStatus'] ?? ''));
        if ($refundStatus !== 'completed' && ($response['statusCode'] ?? '') !== '0000') {
            $errorMessage = $response['errorMessageEn'] ?? 'Refund status not completed.';
            return ['status' => 'error', 'message' => $errorMessage];
        }

        return [
            'status' => 'success',
            'refund_trx_id' => $response['refundTrxId'] ?? null,
            'response' => $response,
        ];
    }

    private function bkashBaseUrl(array $settings): string
    {
        return ! empty($settings['sandbox'])
            ? 'https://tokenized.sandbox.bka.sh/v2'
            : 'https://tokenized.pay.bka.sh/v2';
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

        $cacheKey = 'bkash_token_v2_' . md5($username . $appKey);
        $cachedToken = Cache::get($cacheKey);
        if ($cachedToken) {
            return $cachedToken;
        }

        $baseUrl = $this->bkashBaseUrl($settings);

        $response = Http::timeout(30)
            ->withHeaders([
                'username' => $username,
                'password' => $password,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->post($baseUrl.'/tokenized-checkout/auth/grant-token', [
                'app_key' => $appKey,
                'app_secret' => $appSecret,
            ]);

        Log::info('bKash V2 Grant Token API', [
            'action' => 'Grant Token',
            'request_url' => $baseUrl.'/tokenized-checkout/auth/grant-token',
            'request_body' => [
                'app_key' => $appKey,
                'app_secret' => $appSecret,
            ],
            'response_status' => $response->status(),
            'response_body' => $response->json(),
        ]);

        if (! $response->successful()) {
            SystemLogger::write('module', 'bKash V2 grant token failed.', [
                'response' => $response->json(),
                'status' => $response->status(),
            ], level: 'error');
            return null;
        }

        $idToken = $response->json('id_token');
        if ($idToken) {
            // Cache the token for 50 minutes (3000 seconds)
            Cache::put($cacheKey, $idToken, 3000);
        }

        return $idToken;
    }
}
