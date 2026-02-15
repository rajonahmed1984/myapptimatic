<?php

namespace App\Support\AuthFresh;

use Illuminate\Http\Request;

class Portal
{
    public const SESSION_KEY = 'auth.portal';

    /**
     * @return array<string, array<string, string>>
     */
    public static function map(): array
    {
        return [
            'web' => [
                'guard' => 'web',
                'login_path' => '/login',
                'login_route' => 'login',
                'login_view' => 'auth.login',
                'default_redirect' => 'client.dashboard',
                'recaptcha_action' => 'LOGIN',
            ],
            'admin' => [
                'guard' => 'web',
                'login_path' => '/admin/login',
                'login_route' => 'admin.login',
                'login_view' => 'auth.admin-login',
                'default_redirect' => 'admin.dashboard',
                'recaptcha_action' => 'ADMIN_LOGIN',
            ],
            'employee' => [
                'guard' => 'employee',
                'login_path' => '/employee/login',
                'login_route' => 'employee.login',
                'login_view' => 'employee.auth.login',
                'default_redirect' => 'employee.dashboard',
                'recaptcha_action' => 'EMPLOYEE_LOGIN',
            ],
            'sales' => [
                'guard' => 'sales',
                'login_path' => '/sales/login',
                'login_route' => 'sales.login',
                'login_view' => 'sales.auth.login',
                'default_redirect' => 'rep.dashboard',
                'recaptcha_action' => 'SALES_LOGIN',
            ],
            'support' => [
                'guard' => 'support',
                'login_path' => '/support/login',
                'login_route' => 'support.login',
                'login_view' => 'support.auth.login',
                'default_redirect' => 'support.dashboard',
                'recaptcha_action' => 'SUPPORT_LOGIN',
            ],
        ];
    }

    public static function normalize(?string $portal): string
    {
        return is_string($portal) && isset(self::map()[$portal]) ? $portal : 'web';
    }

    public static function guard(string $portal): string
    {
        return self::map()[self::normalize($portal)]['guard'];
    }

    public static function loginPath(string $portal): string
    {
        return self::map()[self::normalize($portal)]['login_path'];
    }

    public static function loginRoute(string $portal): string
    {
        return self::map()[self::normalize($portal)]['login_route'];
    }

    public static function loginView(string $portal): string
    {
        return self::map()[self::normalize($portal)]['login_view'];
    }

    public static function defaultRedirectRoute(string $portal): string
    {
        return self::map()[self::normalize($portal)]['default_redirect'];
    }

    public static function recaptchaAction(string $portal): string
    {
        return self::map()[self::normalize($portal)]['recaptcha_action'];
    }

    public static function fromRequest(Request $request): string
    {
        $routePortal = $request->route('portal');
        if (is_string($routePortal) && isset(self::map()[$routePortal])) {
            return $routePortal;
        }

        $fromPath = self::fromPath($request->path());
        if ($fromPath !== 'web') {
            return $fromPath;
        }

        $referer = (string) $request->headers->get('referer', '');
        $refererPath = is_string($referer) && $referer !== '' ? parse_url($referer, PHP_URL_PATH) : null;
        if (is_string($refererPath) && $refererPath !== '') {
            return self::fromPath($refererPath);
        }

        return 'web';
    }

    public static function setPortal(Request $request, string $portal): void
    {
        $request->session()->put(self::SESSION_KEY, self::normalize($portal));
    }

    public static function sessionPortal(Request $request): ?string
    {
        $portal = $request->session()->get(self::SESSION_KEY);

        return is_string($portal) && isset(self::map()[$portal]) ? $portal : null;
    }

    public static function portalLoginUrl(string $portal): string
    {
        return self::loginPath($portal);
    }

    /**
     * @return array<int, string>
     */
    public static function guards(): array
    {
        $guards = array_map(
            static fn (array $definition): string => $definition['guard'],
            self::map()
        );

        return array_values(array_unique($guards));
    }

    private static function fromPath(?string $path): string
    {
        $normalized = ltrim((string) $path, '/');

        if ($normalized === 'admin' || str_starts_with($normalized, 'admin/')) {
            return 'admin';
        }

        if ($normalized === 'employee' || str_starts_with($normalized, 'employee/')) {
            return 'employee';
        }

        if ($normalized === 'sales' || str_starts_with($normalized, 'sales/')) {
            return 'sales';
        }

        if ($normalized === 'support' || str_starts_with($normalized, 'support/')) {
            return 'support';
        }

        return 'web';
    }
}
