<?php

namespace App\Services\AuthFresh;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\SalesRepresentative;
use App\Models\User;
use App\Services\RecaptchaService;
use App\Support\AuthFresh\AdminAccess;
use App\Support\AuthFresh\Portal;
use App\Support\SystemLogger;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class LoginService
{
    public const LOGIN_MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly RecaptchaService $recaptcha
    ) {
    }

    public static function registerRateLimiter(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $portal = Portal::fromRequest($request);
            $key = self::limiterKey($request, $portal, (string) $request->input('email', ''));

            return Limit::perMinute(self::LOGIN_MAX_ATTEMPTS)
                ->by($key)
                ->response(function (Request $request) use ($portal) {
                    return redirect(Portal::portalLoginUrl($portal))
                        ->withErrors([
                            'email' => 'Too many login attempts. Please try again in 60 seconds.',
                        ])
                        ->withInput($request->only('email'));
                });
        });
    }

    /**
     * @return array{ok: bool, redirect?: string, error?: string, email?: string}
     */
    public function authenticate(Request $request, string $portal): array
    {
        $portal = Portal::normalize($portal);
        Portal::setPortal($request, $portal);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = (bool) $request->boolean('remember');
        $guard = Portal::guard($portal);

        $this->recaptcha->assertValid($request, Portal::recaptchaAction($portal));

        $authGuard = Auth::guard($guard);
        if (! $authGuard->attempt($credentials, $remember)) {
            $this->logFailure($request, $portal, $guard, $credentials['email'], 'invalid_credentials');

            return [
                'ok' => false,
                'error' => 'Invalid credentials',
                'email' => $credentials['email'],
            ];
        }

        $user = $authGuard->user();
        $validationError = $this->validatePortalAccess($portal, $user);
        if ($validationError !== null) {
            $authGuard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            Portal::setPortal($request, $portal);

            $this->logFailure($request, $portal, $guard, $credentials['email'], $validationError);

            return [
                'ok' => false,
                'error' => $validationError,
                'email' => $credentials['email'],
            ];
        }

        $request->session()->regenerate();
        Portal::setPortal($request, $portal);
        RateLimiter::clear(self::limiterKey($request, $portal, $credentials['email']));

        $safeRedirect = $this->safeRedirectTarget($request);
        if ($safeRedirect !== null) {
            return [
                'ok' => true,
                'redirect' => $safeRedirect,
            ];
        }

        return [
            'ok' => true,
            'redirect' => $this->defaultRedirectUrlFor($portal, $user),
        ];
    }

    public function defaultRedirectUrlFor(string $portal, mixed $user): string
    {
        $portal = Portal::normalize($portal);

        if (! $user instanceof User) {
            return route(Portal::defaultRedirectRoute($portal), [], false);
        }

        if ($portal === 'web') {
            if ($user->isClientProject() && $user->project_id) {
                return route('client.projects.show', $user->project_id, false);
            }
        }

        return route(Portal::defaultRedirectRoute($portal), [], false);
    }

    public static function limiterKey(Request $request, string $portal, string $email): string
    {
        $guard = Portal::guard($portal);
        $normalizedEmail = strtolower(trim($email));
        $ip = (string) ($request->ip() ?? 'unknown');

        return implode('|', ['login', $portal, $guard, $normalizedEmail, $ip]);
    }

    private function validatePortalAccess(string $portal, mixed $user): ?string
    {
        if (! $user instanceof User) {
            return 'Access restricted for this account.';
        }

        if ($portal === 'admin' && ! AdminAccess::canAccess($user)) {
            return 'This account does not have admin access.';
        }

        if ($portal === 'web') {
            if (! in_array($user->role, [Role::CLIENT, Role::CLIENT_PROJECT], true)) {
                return $this->portalAccessMessageForRole($user);
            }

            if ($user->isClientProject() && $user->status === 'inactive') {
                return 'Your account is inactive. Please contact support.';
            }
        }

        if ($portal === 'employee') {
            if ($user->role !== Role::EMPLOYEE) {
                return $this->portalAccessMessageForRole($user);
            }

            $employee = Employee::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            if (! $employee) {
                return 'Access restricted for this account.';
            }
        }

        if ($portal === 'sales') {
            if ($user->role !== Role::SALES) {
                return $this->portalAccessMessageForRole($user);
            }

            $rep = SalesRepresentative::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            if (! $rep) {
                return 'Access restricted for this account.';
            }
        }

        if ($portal === 'support' && $user->role !== Role::SUPPORT) {
            return $this->portalAccessMessageForRole($user);
        }

        return null;
    }

    private function safeRedirectTarget(Request $request): ?string
    {
        $redirect = $request->input('redirect');
        if (! is_string($redirect) || $redirect === '') {
            return null;
        }

        if (str_starts_with($redirect, '/') && ! str_starts_with($redirect, '//')) {
            return $redirect;
        }

        return null;
    }

    private function logFailure(
        Request $request,
        string $portal,
        string $guard,
        string $email,
        string $reason
    ): void {
        SystemLogger::write('admin', 'Portal login denied.', [
            'portal' => $portal,
            'guard' => $guard,
            'email' => $email,
            'reason' => $reason,
        ], null, $request->ip(), 'warning');
    }

    private function portalAccessMessageForRole(User $user): string
    {
        return 'The provided credentials are incorrect.';
    }
}
