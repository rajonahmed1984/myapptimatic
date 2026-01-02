<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use App\Services\ClientNotificationService;
use App\Support\SystemLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
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

    public function login(Request $request): RedirectResponse
    {
        $this->ensureRecaptcha($request, 'LOGIN');

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

        $request->session()->regenerate();

        $user = $request->user();

        SystemLogger::write('admin', 'Login successful.', [
            'login_type' => $user && $user->isAdmin() ? 'admin' : 'client',
            'email' => $user?->email,
            'user_id' => $user?->id,
        ], $user?->id, $request->ip());

        if ($user && $user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $redirect = $this->redirectTarget($request);

        return $redirect
            ? redirect($redirect)
            : redirect()->route('client.dashboard');
    }

    public function adminLogin(Request $request): RedirectResponse
    {
        $this->ensureRecaptcha($request, 'ADMIN_LOGIN');

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

    public function register(Request $request, ClientNotificationService $clientNotifications): RedirectResponse
    {
        $this->ensureRecaptcha($request, 'REGISTER');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
        ]);

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
            'role' => 'client',
            'customer_id' => $customer->id,
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
        $redirectTo = $request->user() && $request->user()->isAdmin()
            ? route('admin.login')
            : route('login');

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect($redirectTo);
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

    private function ensureRecaptcha(Request $request, string $action): void
    {
        $enabled = (bool) config('recaptcha.enabled');
        $siteKey = config('recaptcha.site_key');

        if (! $enabled || ! is_string($siteKey) || $siteKey === '') {
            return;
        }

        $token = $request->input('g-recaptcha-response');
        $secret = config('recaptcha.secret_key');
        $projectId = config('recaptcha.project_id');
        $apiKey = config('recaptcha.api_key');
        $scoreThreshold = (float) config('recaptcha.score_threshold', 0.5);

        if (! is_string($token) || $token === '') {
            throw ValidationException::withMessages([
                'recaptcha' => 'Please complete the reCAPTCHA.',
            ]);
        }

        $isValid = false;

        if (! empty($projectId) && ! empty($apiKey)) {
            $response = Http::timeout(8)->post(
                'https://recaptchaenterprise.googleapis.com/v1/projects/' . rawurlencode($projectId)
                    . '/assessments?key=' . rawurlencode($apiKey),
                [
                    'event' => [
                        'token' => $token,
                        'siteKey' => $siteKey,
                        'expectedAction' => $action,
                    ],
                ]
            );

            $data = $response->json();
            $tokenProps = data_get($data, 'tokenProperties', []);
            $risk = data_get($data, 'riskAnalysis', []);

            $valid = (bool) data_get($tokenProps, 'valid', false);
            $actionValue = data_get($tokenProps, 'action');
            $actionOk = empty($actionValue) || $actionValue === $action;
            $score = data_get($risk, 'score');
            $scoreOk = $score === null || $score >= $scoreThreshold;

            $isValid = $valid && $actionOk && $scoreOk;
        } elseif (! empty($secret)) {
            $response = Http::asForm()->timeout(8)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);

            $data = $response->json();
            $success = (bool) data_get($data, 'success', false);
            $actionValue = data_get($data, 'action');
            $actionOk = empty($actionValue) || $actionValue === $action;
            $score = data_get($data, 'score');
            $scoreOk = $score === null || $score >= $scoreThreshold;

            $isValid = $success && $actionOk && $scoreOk;
        } else {
            throw ValidationException::withMessages([
                'recaptcha' => 'reCAPTCHA is not configured.',
            ]);
        }

        if (! $isValid) {
            throw ValidationException::withMessages([
                'recaptcha' => 'reCAPTCHA verification failed. Please try again.',
            ]);
        }
    }
}
