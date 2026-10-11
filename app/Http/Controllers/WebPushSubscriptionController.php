<?php

namespace App\Http\Controllers;

use App\Models\WebPushSubscription;
use App\Services\Push\WebPushClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The browser (or the installed web app) reports its push subscription here
 * after the user allows notifications, so pushes reach whoever is signed in there.
 */
class WebPushSubscriptionController extends Controller
{
    /** Session key holding this browser's endpoint, so logout can detach it. */
    public const SESSION_KEY = 'web_push_endpoint';

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'url', 'max:2048', function (string $attribute, mixed $value, \Closure $fail) {
                if (! WebPushClient::isAllowedEndpoint((string) $value)) {
                    $fail('Unsupported push service.');
                }
            }],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        WebPushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => WebPushSubscription::hashEndpoint($data['endpoint'])],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
                'last_seen_at' => now(),
            ]
        );

        $request->session()->put(self::SESSION_KEY, $data['endpoint']);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
        ]);

        WebPushSubscription::query()
            ->where('endpoint_hash', WebPushSubscription::hashEndpoint($data['endpoint']))
            ->where('user_id', $request->user()->id)
            ->delete();

        $request->session()->forget(self::SESSION_KEY);

        return response()->json(['ok' => true]);
    }

    /** Stop pushing to this browser once its user signs out. */
    public static function forgetSessionSubscription(Request $request): void
    {
        $endpoint = $request->session()->get(self::SESSION_KEY);

        if (is_string($endpoint) && $endpoint !== '') {
            WebPushSubscription::query()->where('endpoint_hash', WebPushSubscription::hashEndpoint($endpoint))->delete();
        }
    }
}
