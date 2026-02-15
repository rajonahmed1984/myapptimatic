<?php

namespace App\Support\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

class Portal
{
    public const WEB = 'web';
    public const ADMIN = 'admin';
    public const EMPLOYEE = 'employee';
    public const SALES = 'sales';
    public const SUPPORT = 'support';

    public const SESSION_KEY = 'auth.portal';

    /**
     * @return array<string, array<string, string>>
     */
    public static function definitions(): array
    {
        return [
            self::WEB => [
                'guard' => 'web',
                'login_route' => 'login',
                'login_path' => '/login',
                'post_route' => 'login.attempt',
                'login_view' => 'auth.login',
                'recaptcha_action' => 'LOGIN',
                'default_redirect_route' => 'client.dashboard',
            ],
            self::ADMIN => [
                'guard' => 'web',
                'login_route' => 'admin.login',
                'login_path' => '/admin/login',
                'post_route' => 'admin.login.attempt',
                'login_view' => 'auth.admin-login',
                'recaptcha_action' => 'ADMIN_LOGIN',
                'default_redirect_route' => 'admin.dashboard',
            ],
            self::EMPLOYEE => [
                'guard' => 'employee',
                'login_route' => 'employee.login',
                'login_path' => '/employee/login',
                'post_route' => 'employee.login.attempt',
                'login_view' => 'employee.auth.login',
                'recaptcha_action' => 'EMPLOYEE_LOGIN',
                'default_redirect_route' => 'employee.dashboard',
            ],
            self::SALES => [
                'guard' => 'sales',
                'login_route' => 'sales.login',
                'login_path' => '/sales/login',
                'post_route' => 'sales.login.attempt',
                'login_view' => 'sales.auth.login',
                'recaptcha_action' => 'SALES_LOGIN',
                'default_redirect_route' => 'rep.dashboard',
            ],
            self::SUPPORT => [
                'guard' => 'support',
                'login_route' => 'support.login',
                'login_path' => '/support/login',
                'post_route' => 'support.login.attempt',
                'login_view' => 'support.auth.login',
                'recaptcha_action' => 'SUPPORT_LOGIN',
                'default_redirect_route' => 'support.dashboard',
            ],
        ];
    }

    public static function isValid(string $portal): bool
    {
        return array_key_exists($portal, self::definitions());
    }

    public static function normalize(?string $portal): string
    {
        return is_string($portal) && self::isValid($portal) ? $portal : self::WEB;
    }

    public static function guardFor(string $portal): string
    {
        return self::definitions()[self::normalize($portal)]['guard'];
    }

    public static function loginRouteName(string $portal): string
    {
        return self::definitions()[self::normalize($portal)]['login_route'];
    }

    public static function loginPath(string $portal): string
    {
        return self::definitions()[self::normalize($portal)]['login_path'];
    }

    public static function postRouteName(string $portal): string
    {
        return self::definitions()[self::normalize($portal)]['post_route'];
    }

    public static function loginView(string $portal): string
    {
        return self::definitions()[self::normalize($portal)]['login_view'];
    }

    public static function recaptchaAction(string $portal): string
    {
        return self::definitions()[self::normalize($portal)]['recaptcha_action'];
    }

    public static function defaultRedirectRoute(string $portal): string
    {
        return self::definitions()[self::normalize($portal)]['default_redirect_route'];
    }

    public static function setPortalToSession(Request $request, string $portal): void
    {
        $request->session()->put(self::SESSION_KEY, self::normalize($portal));
    }

    public static function getPortalFromSession(Request $request): ?string
    {
        $portal = $request->session()->get(self::SESSION_KEY);
        if (! is_string($portal) || ! self::isValid($portal)) {
            return null;
        }

        return $portal;
    }

    public static function fromRequestPath(?string $path): string
    {
        $normalizedPath = ltrim((string) $path, '/');

        if ($normalizedPath === 'admin' || str_starts_with($normalizedPath, 'admin/')) {
            return self::ADMIN;
        }

        if ($normalizedPath === 'employee' || str_starts_with($normalizedPath, 'employee/')) {
            return self::EMPLOYEE;
        }

        if ($normalizedPath === 'sales' || str_starts_with($normalizedPath, 'sales/')) {
            return self::SALES;
        }

        if ($normalizedPath === 'support' || str_starts_with($normalizedPath, 'support/')) {
            return self::SUPPORT;
        }

        return self::WEB;
    }

    /**
     * @return array<int, string>
     */
    public static function guardNames(): array
    {
        return array_values(array_unique(array_map(
            static fn (array $definition): string => $definition['guard'],
            self::definitions()
        )));
    }

    public static function isAdminAuthorized(Authenticatable|null $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        $allowedRoles = config('admin.panel_roles', Role::adminPanelRoles());

        if (! is_array($allowedRoles) || $allowedRoles === []) {
            $allowedRoles = Role::adminPanelRoles();
        }

        return in_array($user->role, $allowedRoles, true);
    }
}
