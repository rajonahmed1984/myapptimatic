<?php

namespace App\Http\Middleware;

use App\Support\AuthFresh\Portal;
use App\Support\UiFeature;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function rootView(Request $request): string
    {
        if ($request->routeIs('admin.*')) {
            return 'react-admin';
        }

        if ($request->routeIs('client.*')) {
            return 'react-client';
        }

        return 'react-sandbox';
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $portal = Portal::fromRequest($request);

        return array_merge(parent::share($request), [
            'app' => [
                'name' => config('app.name'),
                'env' => config('app.env'),
                'date_format' => config('app.date_format', 'd-m-Y'),
            ],
            'auth' => [
                'user' => $user?->only(['id', 'name', 'email', 'role']),
                'portal' => $portal,
                'guard' => Portal::guard($portal),
            ],
            'permissions' => [
                'is_master_admin' => (bool) ($user && method_exists($user, 'isMasterAdmin') ? $user->isMasterAdmin() : false),
            ],
            'features' => [
                ...UiFeature::all(),
                'active' => [
                    'feature' => $request->attributes->get('react_ui_feature'),
                    'enabled' => (bool) $request->attributes->get('react_ui_enabled', false),
                ],
            ],
            'csrf_token' => csrf_token(),
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ]);
    }
}
