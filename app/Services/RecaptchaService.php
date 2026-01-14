<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RecaptchaService
{
    public function assertValid(Request $request, string $action): void
    {
        $enabled = (bool) config('recaptcha.enabled');
        $siteKey = config('recaptcha.site_key');

        if (! $enabled || ! is_string($siteKey) || $siteKey === '') {
            return;
        }

        $token = $request->input('g-recaptcha-response');

        if (! is_string($token) || $token === '') {
            throw ValidationException::withMessages([
                'recaptcha' => 'Please complete the reCAPTCHA.',
            ]);
        }

        $result = $this->verify($token, $siteKey, $action, $request->ip());

        if ($result === 'not_configured') {
            throw ValidationException::withMessages([
                'recaptcha' => 'reCAPTCHA is not configured.',
            ]);
        }

        if ($result !== true) {
            throw ValidationException::withMessages([
                'recaptcha' => 'reCAPTCHA verification failed. Please try again.',
            ]);
        }
    }

    private function verify(string $token, string $siteKey, string $action, ?string $ip): bool|string
    {
        $secret = config('recaptcha.secret_key');
        $projectId = config('recaptcha.project_id');
        $apiKey = config('recaptcha.api_key');
        $scoreThreshold = (float) config('recaptcha.score_threshold', 0.5);

        try {
            if (! empty($projectId) && ! empty($apiKey)) {
                $response = Http::timeout(8)->post(
                    'https://recaptchaenterprise.googleapis.com/v1/projects/'
                        . rawurlencode($projectId) . '/assessments?key=' . rawurlencode($apiKey),
                    [
                        'event' => [
                            'token' => $token,
                            'siteKey' => $siteKey,
                            'expectedAction' => $action,
                        ],
                    ]
                );

                if (! $response->ok()) {
                    return false;
                }

                $data = $response->json();
                $tokenProps = data_get($data, 'tokenProperties', []);
                $risk = data_get($data, 'riskAnalysis', []);

                $valid = (bool) data_get($tokenProps, 'valid', false);
                $actionValue = data_get($tokenProps, 'action');
                $actionOk = empty($actionValue) || $actionValue === $action;
                $score = data_get($risk, 'score');
                $scoreOk = $score === null || $score >= $scoreThreshold;

                return $valid && $actionOk && $scoreOk;
            }

            if (! empty($secret)) {
                $response = Http::asForm()->timeout(8)->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $ip,
                ]);

                if (! $response->ok()) {
                    return false;
                }

                $data = $response->json();
                $success = (bool) data_get($data, 'success', false);
                $actionValue = data_get($data, 'action');
                $actionOk = empty($actionValue) || $actionValue === $action;
                $score = data_get($data, 'score');
                $scoreOk = $score === null || $score >= $scoreThreshold;

                return $success && $actionOk && $scoreOk;
            }
        } catch (\Throwable $e) {
            Log::warning('reCAPTCHA verification failed.', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        return 'not_configured';
    }
}
