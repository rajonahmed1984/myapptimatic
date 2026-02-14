<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use App\Models\SalesRepresentative;
use App\Services\ClientNotificationService;
use App\Support\Currency;
use App\Enums\Role;
use App\Services\RecaptchaService;
use App\Support\SystemLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function showAdminLogin()
    {
        return view('auth.admin-login');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function login(Request $request, RecaptchaService $recaptcha): RedirectResponse
    {
        $this->loginTrace('ControllerHit', [
            'route' => $request->route()?->getName(),
            'method' => __METHOD__,
            'email' => (string) $request->input('email', ''),
            'guard' => 'web',
        ]);

        $recaptcha->assertValid($request, 'LOGIN');

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');
        $webGuard = Auth::guard('web');

        $this->loginTrace('Attempting', [
            'guard' => 'web',
            'email' => $credentials['email'],
            'remember' => $remember,
        ]);

        $attempted = $webGuard->attempt($credentials, $remember);

        $this->loginTrace('AttemptResult', [
            'guard' => 'web',
            'result' => $attempted,
            'user_id' => $webGuard->id(),
        ]);

        if (! $attempted) {
            SystemLogger::write('admin', 'Login failed.', [
                'login_type' => 'client',
                'email' => $credentials['email'],
            ], null, $request->ip(), 'warning');

            $this->loginTrace('Response', [
                'guard' => 'web',
                'type' => 'redirect',
                'status' => 302,
                'target_route' => 'login',
            ]);

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'The provided credentials are incorrect.'])
                ->withInput($request->only('email'));
        }

        $user = $webGuard->user();

        if ($user && $user->isClientProject() && $user->status === 'inactive') {
            SystemLogger::write('admin', 'Project client login denied (inactive).', [
                'login_type' => 'project_client',
                'email' => $user->email,
                'user_id' => $user->id,
            ], $user->id, $request->ip(), 'warning');

            $webGuard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $this->loginTrace('Response', [
                'guard' => 'web',
                'type' => 'redirect',
                'status' => 302,
                'target_route' => 'login',
                'reason' => 'inactive_project_client',
            ]);

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Your account is inactive. Please contact support.'])
                ->withInput($request->only('email'));
        }

        $request->session()->regenerate();

        SystemLogger::write('admin', 'Login successful.', [
            'login_type' => $user && $user->isAdmin() ? 'admin' : 'client',
            'email' => $user?->email,
            'user_id' => $user?->id,
        ], $user?->id, $request->ip());

        // Handle project-specific clients
        if ($user && $user->isClientProject() && $user->project_id) {
            $this->loginTrace('Response', [
                'guard' => 'web',
                'type' => 'redirect',
                'status' => 302,
                'target_route' => 'client.projects.show',
                'project_id' => $user->project_id,
            ]);
            return redirect()->route('client.projects.show', $user->project_id);
        }

        if ($user && $user->isAdmin()) {
            $this->loginTrace('Response', [
                'guard' => 'web',
                'type' => 'redirect',
                'status' => 302,
                'target_route' => 'admin.dashboard',
            ]);
            return redirect()->route('admin.dashboard');
        }

        $activeRep = $user
            ? SalesRepresentative::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first()
            : null;

        if ($activeRep) {
            Auth::guard('sales')->login($user, $remember);
            $this->loginTrace('Response', [
                'guard' => 'web',
                'type' => 'redirect',
                'status' => 302,
                'target_route' => 'rep.dashboard',
                'sales_user_id' => $user?->id,
            ]);
            return redirect()->route('rep.dashboard');
        }

        $redirect = $this->redirectTarget($request);

        if ($redirect) {
            $this->loginTrace('Response', [
                'guard' => 'web',
                'type' => 'redirect',
                'status' => 302,
                'target_url' => $redirect,
            ]);

            return redirect($redirect);
        }

        $this->loginTrace('Response', [
            'guard' => 'web',
            'type' => 'redirect',
            'status' => 302,
            'target_route' => 'client.dashboard',
        ]);

        return redirect()->route('client.dashboard');
    }

    public function adminLogin(Request $request, RecaptchaService $recaptcha): RedirectResponse
    {
        $this->loginTrace('ControllerHit', [
            'route' => $request->route()?->getName(),
            'method' => __METHOD__,
            'email' => (string) $request->input('email', ''),
            'guard' => 'web',
        ]);

        $recaptcha->assertValid($request, 'ADMIN_LOGIN');

        $sessionCookieName = config('session.cookie');
        $incomingCookieSessionId = is_string($sessionCookieName)
            ? $request->cookie($sessionCookieName)
            : null;
        $incomingSessionId = $request->hasSession() ? $request->session()->getId() : null;

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');
        $webGuard = Auth::guard('web');

        $this->loginTrace('Attempting', [
            'guard' => 'web',
            'email' => $credentials['email'],
            'remember' => $remember,
            'panel' => 'admin',
        ]);

        $attempted = $webGuard->attempt($credentials, $remember);

        $this->loginTrace('AttemptResult', [
            'guard' => 'web',
            'result' => $attempted,
            'user_id' => $webGuard->id(),
            'panel' => 'admin',
        ]);

        if (! $attempted) {
            SystemLogger::write('admin', 'Admin login failed.', [
                'login_type' => 'admin',
                'email' => $credentials['email'],
            ], null, $request->ip(), 'warning');

            $this->loginTrace('Response', [
                'guard' => 'web',
                'type' => 'redirect',
                'status' => 302,
                'target_route' => 'admin.login',
                'panel' => 'admin',
            ]);

            return redirect()
                ->route('admin.login')
                ->withErrors(['email' => 'The provided credentials are incorrect.'])
                ->withInput($request->only('email'));
        }

        $request->session()->regenerate();

        $user = $webGuard->user();

        if (! $user || ! $user->isAdmin()) {
            SystemLogger::write('admin', 'Admin login denied.', [
                'login_type' => 'admin',
                'email' => $user?->email,
                'user_id' => $user?->id,
            ], $user?->id, $request->ip(), 'warning');

            $webGuard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $this->loginTrace('Response', [
                'guard' => 'web',
                'type' => 'redirect',
                'status' => 302,
                'target_route' => 'admin.login',
                'panel' => 'admin',
                'reason' => 'not_admin',
            ]);

            return redirect()
                ->route('admin.login')
                ->withErrors(['email' => 'This account does not have admin access.'])
                ->withInput($request->only('email'));
        }

        $sessionLoginKeys = array_values(array_filter(
            array_keys($request->session()->all()),
            static fn (string $key): bool => str_starts_with($key, 'login_')
        ));

        SystemLogger::write('admin', 'Admin login.', [
            'email' => $user->email,
            'user_id' => $user->id,
            'incoming_session_id' => $incomingSessionId,
            'incoming_cookie_session_id' => $incomingCookieSessionId,
            'post_login_session_id' => $request->session()->getId(),
            'session_login_keys' => $sessionLoginKeys,
            'session_driver' => config('session.driver'),
            'session_domain' => config('session.domain'),
            'app_url' => config('app.url'),
            'app_key_sha1' => sha1((string) config('app.key')),
        ], $user->id, $request->ip());

        $this->loginTrace('Response', [
            'guard' => 'web',
            'type' => 'redirect',
            'status' => 302,
            'target_route' => 'admin.dashboard',
            'panel' => 'admin',
        ]);

        return redirect()->route('admin.dashboard');
    }

    public function register(Request $request, ClientNotificationService $clientNotifications, RecaptchaService $recaptcha): RedirectResponse
    {
        $recaptcha->assertValid($request, 'REGISTER');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'currency' => ['nullable', Rule::in(Currency::allowed())],
        ]);

        $currency = strtoupper((string) ($data['currency'] ?? Currency::DEFAULT));
        if (! Currency::isAllowed($currency)) {
            $currency = Currency::DEFAULT;
        }

        $customer = Customer::create([
            'name' => $data['name'],
            'company_name' => $data['company_name'] ?? null,
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'status' => 'active',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
            'currency' => $currency,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        $clientNotifications->sendClientSignup($customer);

        $redirect = $this->redirectTarget($request);

        if ($redirect) {
            return redirect($redirect)
                ->with('status', 'Welcome! Your account is ready.');
        }

        return redirect()
            ->route('client.dashboard')
            ->with('status', 'Welcome! Your account is ready.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function logoutAdmin(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        Auth::guard('support')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    public function stopImpersonate(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->pull('impersonator_id');

        if (! $impersonatorId) {
            return redirect()->route('client.dashboard');
        }

        $admin = User::find($impersonatorId);

        if (! $admin || ! $admin->isAdmin()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login');
        }

        Auth::guard('web')->login($admin);
        Auth::guard('sales')->logout();
        Auth::guard('support')->logout();
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    private function redirectTarget(Request $request): ?string
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

    private function loginTrace(string $event, array $context = []): void
    {
        if (! config('app.login_trace')) {
            return;
        }

        Log::info('[LOGIN_TRACE] ' . $event, $context);
    }

}
