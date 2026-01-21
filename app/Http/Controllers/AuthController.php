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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        $recaptcha->assertValid($request, 'LOGIN');

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            SystemLogger::write('admin', 'Login failed.', [
                'login_type' => 'client',
                'email' => $credentials['email'],
            ], null, $request->ip(), 'warning');

            throw ValidationException::withMessages([
                'email' => 'The provided credentials are incorrect.',
            ]);
        }

        $user = $request->user();

        if ($user && $user->isClientProject() && $user->status === 'inactive') {
            SystemLogger::write('admin', 'Project client login denied (inactive).', [
                'login_type' => 'project_client',
                'email' => $user->email,
                'user_id' => $user->id,
            ], $user->id, $request->ip(), 'warning');

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Your account is inactive. Please contact support.',
            ]);
        }

        $request->session()->regenerate();

        SystemLogger::write('admin', 'Login successful.', [
            'login_type' => $user && $user->isAdmin() ? 'admin' : 'client',
            'email' => $user?->email,
            'user_id' => $user?->id,
        ], $user?->id, $request->ip());

        // Handle project-specific clients
        if ($user && $user->isClientProject() && $user->project_id) {
            return redirect()->route('client.projects.show', $user->project_id);
        }

        if ($user && $user->isAdmin()) {
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
            return redirect()->route('rep.dashboard');
        }

        $redirect = $this->redirectTarget($request);

        return $redirect
            ? redirect($redirect)
            : redirect()->route('client.dashboard');
    }

    public function adminLogin(Request $request, RecaptchaService $recaptcha): RedirectResponse
    {
        $recaptcha->assertValid($request, 'ADMIN_LOGIN');

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            SystemLogger::write('admin', 'Admin login failed.', [
                'login_type' => 'admin',
                'email' => $credentials['email'],
            ], null, $request->ip(), 'warning');

            throw ValidationException::withMessages([
                'email' => 'The provided credentials are incorrect.',
            ]);
        }

        $request->session()->regenerate();

        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            SystemLogger::write('admin', 'Admin login denied.', [
                'login_type' => 'admin',
                'email' => $user?->email,
                'user_id' => $user?->id,
            ], $user?->id, $request->ip(), 'warning');

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'This account does not have admin access.',
            ]);
        }

        SystemLogger::write('admin', 'Admin login.', [
            'email' => $user->email,
        ], $user->id, $request->ip());

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
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login');
        }

        Auth::login($admin);
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

}
