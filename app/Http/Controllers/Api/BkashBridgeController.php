<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BkashBridgeController extends Controller
{
    public function getToken()
    {
        $settings = config('services.bkash');

        $username = $settings['username'] ?? null;
        $password = $settings['password'] ?? null;
        $appKey = $settings['app_key'] ?? null;
        $appSecret = $settings['app_secret'] ?? null;

        if (!$username || !$password || !$appKey || !$appSecret) {
            return response()->json(['message' => 'bKash credentials are not configured on Token Master.'], 500);
        }

        $cacheKey = 'bkash_token_bridge_master';

        try {
            $idToken = Cache::remember($cacheKey, 3300, function () use ($settings, $username, $password, $appKey, $appSecret) {
                $isSandbox = (bool) ($settings['sandbox'] ?? true);
                $baseUrl = $isSandbox
                    ? 'https://tokenized.sandbox.bka.sh/v2'
                    : 'https://tokenized.pay.bka.sh/v2';

                $response = Http::timeout(30)
                    ->withHeaders([
                        'username' => $username,
                        'password' => $password,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ])
                    ->post($baseUrl . '/tokenized-checkout/auth/grant-token', [
                        'app_key' => $appKey,
                        'app_secret' => $appSecret,
                    ]);

                Log::info('bKash Bridge Master Grant Token API', [
                    'action' => 'Grant Token Bridge',
                    'response_status' => $response->status(),
                ]);

                if (!$response->successful() || !$response->json('id_token')) {
                    throw new \Exception('Failed to grant token from bKash API: ' . $response->body());
                }

                return $response->json('id_token');
            });

            return response()->json(['id_token' => $idToken]);
        } catch (\Throwable $e) {
            Log::error('bKash Bridge token fetch error', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to retrieve bKash token.',
                'error' => $e->getMessage()
            ], 502);
        }
    }
}
