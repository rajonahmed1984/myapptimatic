<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\SalesRepresentative;
use App\Models\User;
use App\Services\AdminNotificationService;
use App\Services\ClientNotificationService;
use App\Services\RecaptchaService;
use App\Services\SalesReferralService;
use App\Support\SystemLogger;
use App\Support\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AuthController extends Controller
{
    public const ACCOUNT_CUSTOMER = 'customer';

    public const ACCOUNT_SALES_REP = 'sales_rep';

    /**
     * One sign-up page for customers and sales representatives.
     *
     * A signed-in customer may still open it to apply as a sales rep (with a
     * different email); anyone else already signed in is sent to their portal.
     */
    public function showRegister(Request $request, SalesReferralService $referrals): InertiaResponse|RedirectResponse
    {
        $accountType = $this->accountType($request);

        if ($accountType !== self::ACCOUNT_SALES_REP && Auth::guard('web')->check()) {
            return redirect()->route('client.dashboard');
        }

        $redirect = $this->redirectTarget($request);
        $referredBy = $referrals->referringRep($request);

        return Inertia::render('Auth/Register', [
            'form' => [
                'account_type' => $accountType,
                'name' => old('name', ''),
                'company_name' => old('company_name', ''),
                'email' => old('email', ''),
                'phone_country' => old('phone_country', '+880'),
                'phone' => old('phone', ''),
                'address' => old('address', ''),
                'currency' => old('currency', 'BDT'),
                'redirect' => $redirect,
            ],
            'referred_by' => $referredBy ? ['name' => (string) $referredBy->name] : null,
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

    public function register(
        Request $request,
        ClientNotificationService $clientNotifications,
        AdminNotificationService $adminNotifications,
        RecaptchaService $recaptcha,
        SalesReferralService $referrals
    ): RedirectResponse {
        $accountType = $this->accountType($request);

        if ($accountType !== self::ACCOUNT_SALES_REP && Auth::guard('web')->check()) {
            return redirect()->route('client.dashboard');
        }

        $recaptcha->assertValid($request, 'REGISTER');

        if ($accountType === self::ACCOUNT_SALES_REP) {
            return $this->registerSalesRep($request, $adminNotifications);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'phone_country' => ['nullable', 'string', 'max:6'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'currency' => ['nullable', Rule::in(Currency::allowed())],
        ]);

        $currency = strtoupper((string) ($data['currency'] ?? Currency::DEFAULT));
        if (! Currency::isAllowed($currency)) {
            $currency = Currency::DEFAULT;
        }

        $phone = $this->normalizePhone($data['phone'] ?? null, $data['phone_country'] ?? null);

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

        $referrals->creditNewCustomer($customer, $request);

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

    /**
     * A sales rep signs up to a pending account; an admin approves it before
     * the rep can sign in or share a referral link.
     */
    private function registerSalesRep(Request $request, AdminNotificationService $adminNotifications): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'phone_country' => ['nullable', 'string', 'max:6'],
            'phone' => ['required', 'string', 'max:50'],
        ]);

        $rep = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => Role::SALES,
            ]);

            return SalesRepresentative::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $this->normalizePhone($data['phone'], $data['phone_country'] ?? null),
                'status' => SalesRepresentative::STATUS_PENDING,
            ]);
        });

        SystemLogger::write('activity', 'Sales representative signed up.', [
            'sales_representative_id' => $rep->id,
            'email' => $rep->email,
        ], null, $request->ip());

        $adminNotifications->sendSalesRepSignup($rep);

        $message = 'Thanks for signing up as a sales representative. An admin will review your account; you can sign in here once it is approved.';

        if (Auth::guard('web')->check()) {
            return redirect()->route('client.dashboard')->with('status', $message);
        }

        return redirect()->route('login')->with('status', $message);
    }

    private function accountType(Request $request): string
    {
        $type = (string) ($request->input('account_type') ?: $request->query('as', ''));

        return $type === self::ACCOUNT_SALES_REP ? self::ACCOUNT_SALES_REP : self::ACCOUNT_CUSTOMER;
    }

    private function normalizePhone(?string $phone, ?string $country): ?string
    {
        $country = trim((string) ($country ?? '+880'));
        if ($country === '' || ! str_starts_with($country, '+')) {
            $country = '+880';
        }

        $number = preg_replace('/\s+/', '', (string) ($phone ?? ''));

        if ($number === '') {
            return null;
        }

        return str_starts_with($number, '+') ? $number : $country.ltrim($number, '0');
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
