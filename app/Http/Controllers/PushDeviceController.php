<?php

namespace App\Http\Controllers;

use App\Models\PushDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The mobile app reports its Firebase token here after the user signs in, so
 * pushes reach whoever is signed in on that install.
 */
class PushDeviceController extends Controller
{
    /** Session key holding this install's token, so logout can detach it. */
    public const SESSION_KEY = 'push_device_token';

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'in:android,ios'],
        ]);

        PushDevice::query()->updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $data['platform'] ?? 'android',
                'last_seen_at' => now(),
            ]
        );

        $request->session()->put(self::SESSION_KEY, $data['token']);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        PushDevice::query()
            ->where('token', $data['token'])
            ->where('user_id', $request->user()->id)
            ->delete();

        $request->session()->forget(self::SESSION_KEY);

        return response()->json(['ok' => true]);
    }

    /** Stop pushing to this install once its user signs out. */
    public static function forgetSessionDevice(Request $request): void
    {
        $token = $request->session()->get(self::SESSION_KEY);

        if (is_string($token) && $token !== '') {
            PushDevice::query()->where('token', $token)->delete();
        }
    }
}
