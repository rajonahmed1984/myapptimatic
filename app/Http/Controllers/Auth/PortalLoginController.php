<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SalesRepresentative;
use App\Models\User;
use App\Services\RecaptchaService;
use App\Support\Auth\Portal;
use App\Support\Auth\RateLimiters;
use App\Support\SystemLogger;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PortalLoginController extends Controller
{
    public function showLogin(Request $request, string $portal): View|RedirectResponse
    {
        $portal = Portal::normalize($portal);
        Portal::setPortalToSession($request, $portal);

        $guard = Portal::guardFor($portal);
        if (Auth::guard($guard)->check()) {
            return redirect($this->defaultRedirectUrlFor($portal, Auth::guard($guard)->user()));
        }

        return view(Portal::loginView($portal));
    }

    public function login(Request $request, string $portal, RecaptchaService $recaptcha): RedirectResponse
    {
        $portal = Portal::normalize($portal);
        Portal::setPortalToSession($request, $portal);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = (bool) $request->boolean('remember');
        $guard = Portal::guardFor($portal);

        $recaptcha->assertValid($request, Portal::recaptchaAction($portal));

        $authGuard = Auth::guard($guard);
        $attempted = $authGuard->attempt($credentials, $remember);

        if (! $attempted) {
            SystemLogger::write('admin', 'Portal login failed.', [
                'portal' => $portal,
                'guard' => $guard,
                'email' => $credentials['email'],
            ], null, $request->ip(), 'warning');

            return redirect()
                ->route(Portal::loginRouteName($portal))
                ->withErrors(['email' => 'Invalid credentials'])
                ->withInput($request->only('email'));
        }

        $user = $authGuard->user();
        $validationError = $this->validatePortalAccess($portal, $user);
        if ($validationError !== null) {
            $authGuard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            Portal::setPortalToSession($request, $portal);

            SystemLogger::write('admin', 'Portal login denied.', [
                'portal' => $portal,
                'guard' => $guard,
                'email' => $credentials['email'],
                'reason' => $validationError,
                'user_id' => $user instanceof User ? $user->id : null,
            ], $user instanceof User ? $user->id : null, $request->ip(), 'warning');

            return redirect()
                ->route(Portal::loginRouteName($portal))
                ->withErrors(['email' => $validationError])
                ->withInput($request->only('email'));
        }

        $request->session()->regenerate();
        Portal::setPortalToSession($request, $portal);
        RateLimiters::clear($request, $portal, $credentials['email']);

        $safeRedirect = $this->safeRedirectTarget($request);
        if ($safeRedirect !== null) {
            return redirect($safeRedirect);
        }

        return redirect()->intended($this->defaultRedirectUrlFor($portal, $user));
    }

    private function validatePortalAccess(string $portal, Authenticatable|null $user): ?string
    {
        if (! $user instanceof User) {
            return 'Access restricted for this account.';
        }

        if ($portal === Portal::ADMIN && ! Portal::isAdminAuthorized($user)) {
            return 'This account does not have admin access.';
        }

        if ($portal === Portal::WEB) {
            if ($user->isClientProject() && $user->status === 'inactive') {
                return 'Your account is inactive. Please contact support.';
            }

            if (! in_array($user->role, [Role::CLIENT, Role::CLIENT_PROJECT], true) && ! Portal::isAdminAuthorized($user)) {
                return 'Use your assigned portal login.';
            }
        }

        if ($portal === Portal::EMPLOYEE) {
            $employee = Employee::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            if (! $employee) {
                return 'Access restricted for this account.';
            }
        }

        if ($portal === Portal::SALES) {
            if ($user->role !== Role::SALES) {
                return 'Access restricted for this account.';
            }

            $rep = SalesRepresentative::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            if (! $rep) {
                return 'Access restricted for this account.';
            }
        }

        if ($portal === Portal::SUPPORT && $user->role !== Role::SUPPORT) {
            return 'Access restricted for this account.';
        }

        return null;
    }

    private function defaultRedirectUrlFor(string $portal, Authenticatable|null $user): string
    {
        if (! $user instanceof User) {
            return route(Portal::defaultRedirectRoute($portal), [], false);
        }

        if ($portal === Portal::WEB) {
            if ($user->isClientProject() && $user->project_id) {
                return route('client.projects.show', $user->project_id, false);
            }

            if (Portal::isAdminAuthorized($user)) {
                return route('admin.dashboard', [], false);
            }
        }

        return route(Portal::defaultRedirectRoute($portal), [], false);
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
}
