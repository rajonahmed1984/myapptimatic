<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use App\Services\ClientNotificationService;
use App\Support\Currency;
use App\Enums\Role;
use App\Services\RecaptchaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AuthController extends Controller
{
    public function showRegister(Request $request): InertiaResponse
    {
        $redirect = $this->redirectTarget($request);

        return Inertia::render('Auth/Register', [
            'form' => [
                'name' => old('name', ''),
                'company_name' => old('company_name', ''),
                'email' => old('email', ''),
                'phone_country' => old('phone_country', '+880'),
                'phone' => old('phone', ''),
                'address' => old('address', ''),
                'currency' => old('currency', 'BDT'),
                'accepttos' => (bool) old('accepttos', false),
                'redirect' => $redirect,
            ],
            'routes' => [
                'submit' => route('register.store', [], false),
                'login' => route('login', $redirect ? ['redirect' => $redirect] : [], false),
            ],
            'recaptcha' => [
                'enabled' => (bool) config('recaptcha.enabled') && is_string(config('recaptcha.site_key')) && config('recaptcha.site_key') !== '',
                'site_key' => (string) config('recaptcha.site_key', ''),
                'action' => 'REGISTER',
            ],
        ]);
    }

    public function register(Request $request, ClientNotificationService $clientNotifications, RecaptchaService $recaptcha): RedirectResponse
    {
        $recaptcha->assertValid($request, 'REGISTER');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'phone_country' => ['nullable', 'string', 'max:6'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'currency' => ['nullable', Rule::in(Currency::allowed())],
            'accepttos' => ['accepted'],
        ], [
            'accepttos.accepted' => 'Please accept the Terms of Service to continue registration.',
        ]);

        $currency = strtoupper((string) ($data['currency'] ?? Currency::DEFAULT));
        if (! Currency::isAllowed($currency)) {
            $currency = Currency::DEFAULT;
        }

        $phoneCountry = trim((string) ($data['phone_country'] ?? '+880'));
        if ($phoneCountry === '' || ! str_starts_with($phoneCountry, '+')) {
            $phoneCountry = '+880';
        }

        $phoneNumber = preg_replace('/\s+/', '', (string) ($data['phone'] ?? ''));
        $phone = null;
        if ($phoneNumber !== '') {
            $phone = str_starts_with($phoneNumber, '+')
                ? $phoneNumber
                : ($phoneCountry . ltrim($phoneNumber, '0'));
        }

        $customer = Customer::create([
            'name' => $data['name'],
            'company_name' => $data['company_name'] ?? null,
            'email' => $data['email'],
            'phone' => $phone,
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
}
