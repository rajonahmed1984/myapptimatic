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
        if ($request->routeIs(
            'login',
            'admin.login',
            'employee.login',
            'sales.login',
            'support.login',
            'register',
            'password.request',
            'password.reset',
            'admin.password.request',
            'employee.password.request',
            'employee.password.reset',
            'sales.password.request',
            'sales.password.reset',
            'support.password.request',
            'support.password.reset',
            'project-client.login'
        )) {
            return 'react-guest';
        }

        if ($request->routeIs('products.public.*')) {
            return 'react-public';
        }

        if ($request->routeIs('admin.*')) {
            return 'react-admin';
        }

        if ($request->routeIs('employee.*')) {
            return 'react-employee';
        }

        if ($request->routeIs('client.*')) {
            return 'react-client';
        }

        if ($request->routeIs('rep.*')) {
            return 'react-rep';
        }

        if ($request->routeIs('support.*')) {
            return 'react-support';
        }

        return 'react-sandbox';
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $portal = Portal::fromRequest($request);
        $portalBranding = view()->shared('portalBranding');
        if (! is_array($portalBranding)) {
            $portalBranding = [];
        }

        return array_merge(parent::share($request), [
            'app' => [
                'name' => config('app.name'),
                'env' => config('app.env'),
                'date_format' => config('app.date_format', 'd-m-Y'),
                'time_format' => config('app.time_format', 'h:i A'),
                'datetime_format' => config('app.datetime_format', 'd-m-Y h:i A'),
            ],
            'branding' => [
                'company_name' => (string) ($portalBranding['company_name'] ?? config('app.name')),
                'logo_url' => $portalBranding['logo_url'] ?? null,
                'favicon_url' => $portalBranding['favicon_url'] ?? null,
            ],
            'routes' => [
                'home' => url('/'),
                'login' => route('login', [], false),
                'register' => route('register', [], false),
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
            'page' => [
                'route_name' => optional($request->route())->getName(),
                'url' => $request->getRequestUri(),
                'path' => $request->path(),
            ],
            'csrf_token' => csrf_token(),
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ]);
    }
}
