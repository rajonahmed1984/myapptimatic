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
        return 'app';
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $portal = Portal::fromRequest($request);
        $portalBranding = view()->shared('portalBranding');
        if (! is_array($portalBranding)) {
            $portalBranding = [];
        }

        $adminHeaderStats = view()->shared('adminHeaderStats') ?? [];
        $employeeHeaderStats = view()->shared('employeeHeaderStats') ?? [];
        $clientHeaderStats = view()->shared('clientHeaderStats') ?? [];
        $repHeaderStats = view()->shared('repHeaderStats') ?? [];

        $avatarUrl = null;
        if ($user) {
            $avatarPath = $user->employee?->photo_path ?? $user->avatar_path;
            $avatarUrl = \App\Support\PublicStorageUrl::fromPath(is_string($avatarPath) ? $avatarPath : null);
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
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'avatar_url' => $avatarUrl,
                    'employee' => $user->employee ? [
                        'id' => $user->employee->id,
                        'work_mode' => (string) ($user->employee->work_mode ?? ''),
                        'employment_type' => (string) ($user->employee->employment_type ?? ''),
                    ] : null,
                ] : null,
                'portal' => $portal,
                'guard' => Portal::guard($portal),
                'is_impersonating' => $request->session()->has('impersonator_id'),
            ],
            'stats' => [
                'admin' => $adminHeaderStats,
                'employee' => $employeeHeaderStats,
                'client' => $clientHeaderStats,
                'rep' => $repHeaderStats,
            ],
            'permissions' => [
                'is_master_admin' => (bool) ($user && method_exists($user, 'isMasterAdmin') ? $user->isMasterAdmin() : false),
                'can_view_tasks' => (bool) ($user && app(\App\Services\TaskQueryService::class)->canViewTasks($user)),
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
                'social_error' => fn () => $request->session()->get('social_error'),
            ],
        ]);
    }
}
