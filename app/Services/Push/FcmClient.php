<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends one message to one device through the Firebase Cloud Messaging HTTP v1
 * API. Authenticates with the Firebase service account key (a JSON file from
 * Firebase console > Project settings > Service accounts), signing the OAuth
 * assertion locally so no Google SDK is needed.
 */
class FcmClient
{
    public const SENT = 'sent';

    /** The token no longer belongs to an install (uninstalled, data cleared). */
    public const INVALID_TOKEN = 'invalid_token';

    public const FAILED = 'failed';

    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private ?array $credentials = null;

    public function isConfigured(): bool
    {
        try {
            $this->credentials();

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * @param  array<string, scalar|null>  $data  Delivered to the app alongside the notification.
     */
    public function send(string $token, string $title, string $body, array $data = []): string
    {
        $credentials = $this->credentials();

        $response = Http::withToken($this->accessToken())
            ->timeout(10)
            ->post("https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send", [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    // FCM only accepts string values in data.
                    'data' => (object) array_map(
                        fn ($value) => (string) $value,
                        array_filter($data, fn ($value) => $value !== null)
                    ),
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'channel_id' => (string) config('services.fcm.channel_id', 'general'),
                            'sound' => 'default',
                        ],
                    ],
                    'apns' => [
                        'payload' => ['aps' => ['sound' => 'default']],
                    ],
                ],
            ]);

        if ($response->successful()) {
            return self::SENT;
        }

        $errorCode = collect($response->json('error.details', []))
            ->pluck('errorCode')
            ->filter()
            ->first();

        if ($response->status() === 404 || $errorCode === 'UNREGISTERED'
            || ($errorCode === 'INVALID_ARGUMENT' && str_contains((string) $response->json('error.message'), 'registration token'))) {
            return self::INVALID_TOKEN;
        }

        if ($response->status() === 401) {
            Cache::forget($this->tokenCacheKey());
        }

        report(new RuntimeException('FCM send failed ('.$response->status().'): '.$response->body()));

        return self::FAILED;
    }

    private function accessToken(): string
    {
        return Cache::remember($this->tokenCacheKey(), now()->addMinutes(50), function () {
            $credentials = $this->credentials();
            $tokenUri = $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token';
            $now = time();

            $assertion = $this->signJwt([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => $tokenUri,
                'iat' => $now,
                'exp' => $now + 3600,
            ], $credentials['private_key']);

            $response = Http::asForm()->timeout(10)->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

            $accessToken = (string) $response->json('access_token', '');
            if (! $response->successful() || $accessToken === '') {
                throw new RuntimeException('Could not get a Firebase access token ('.$response->status().'): '.$response->body());
            }

            return $accessToken;
        });
    }

    private function signJwt(array $claims, string $privateKey): string
    {
        $encode = fn (string $value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $unsigned = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
            .'.'.$encode(json_encode($claims, JSON_UNESCAPED_SLASHES));

        $key = openssl_pkey_get_private($privateKey);
        if ($key === false || ! openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('The Firebase service account private key could not be used to sign.');
        }

        return $unsigned.'.'.$encode($signature);
    }

    private function credentials(): array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        $path = (string) config('services.fcm.credentials');
        if ($path !== '' && ! preg_match('#^([a-zA-Z]:)?[\\\\/]#', $path)) {
            $path = base_path($path);
        }

        if ($path === '' || ! is_file($path)) {
            throw new RuntimeException("Firebase service account file not found at [{$path}].");
        }

        $credentials = json_decode((string) file_get_contents($path), true);
        if (! is_array($credentials) || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            throw new RuntimeException("Firebase service account file [{$path}] is not a valid service account key.");
        }

        $credentials['project_id'] = (string) (config('services.fcm.project_id') ?: ($credentials['project_id'] ?? ''));
        if ($credentials['project_id'] === '') {
            throw new RuntimeException('Firebase project id is missing from the service account file.');
        }

        return $this->credentials = $credentials;
    }

    private function tokenCacheKey(): string
    {
        return 'fcm:access-token:'.md5((string) config('services.fcm.credentials'));
    }
}
