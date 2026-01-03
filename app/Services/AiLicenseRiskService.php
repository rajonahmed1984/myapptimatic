<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AiLicenseRiskService
{
    public function score(array $payload): array
    {
        if (! config('ai.enabled') || ! config('ai.license_risk_enabled')) {
            return ['risk_score' => 0.0, 'decision' => 'allow', 'reason' => 'ai_disabled'];
        }

        $url = config('ai.license_risk_url');
        if (! $url) {
            return ['risk_score' => 0.0, 'decision' => 'allow', 'reason' => 'missing_url'];
        }

        $body = json_encode($payload);
        $timestamp = (string) now()->timestamp;
        $secret = config('ai.hmac_secret');
        $signature = $secret ? hash_hmac('sha256', $timestamp.'.'.$body, $secret) : null;
        $token = config('ai.token');
        $timeout = (int) config('ai.timeout', 5);

        $response = Http::timeout($timeout)
            ->withHeaders(array_filter([
                'Authorization' => $token ? 'Bearer '.$token : null,
                'X-Timestamp' => $timestamp,
                'X-Signature' => $signature,
            ]))
            ->withBody($body, 'application/json')
            ->post($url);

        if (! $response->successful()) {
            return ['risk_score' => 0.0, 'decision' => 'allow', 'reason' => 'ai_unreachable'];
        }

        return [
            'risk_score' => (float) $response->json('risk_score', 0.0),
            'decision' => (string) $response->json('decision', 'allow'),
            'reason' => (string) $response->json('reason', 'ok'),
            'details' => $response->json('details', []),
        ];
    }
}
