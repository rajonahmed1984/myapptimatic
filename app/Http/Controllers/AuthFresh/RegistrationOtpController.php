<?php

namespace App\Http\Controllers\AuthFresh;

use App\Enums\Role;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SalesRepresentative;
use App\Models\User;
use App\Services\AdminNotificationService;
use App\Services\AuthFresh\LoginOtpService;
use App\Services\AuthFresh\RegistrationOtpService;
use App\Services\ClientNotificationService;
use App\Services\SalesReferralService;
use App\Support\SystemLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class RegistrationOtpController extends Controller
{
    public function __construct(
        private readonly RegistrationOtpService $otpService,
        private readonly LoginOtpService $loginOtpService
    ) {
    }

    public function show(Request $request): InertiaResponse|RedirectResponse
    {
        $pending = $this->otpService->pending($request);
        if (! $pending) {
            return redirect()->route('register');
        }

        return Inertia::render('Auth/VerifyOtp', [
            'pageTitle' => 'Verify your email',
            'heading' => 'Verify your email',
            'submitLabel' => 'Verify & Complete Registration',
            'cancelLabel' => 'Back to registration',
            'email' => $this->loginOtpService->maskEmail((string) $pending['data']['email']),
            'isLocal' => LoginOtpService::isLocalDev(),
            'localTempOtp' => LoginOtpService::isLocalDev() ? LoginOtpService::LOCAL_TEMP_OTP : null,
            'routes' => [
                'submit' => route('register.otp.verify', [], false),
                'resend' => route('register.otp.resend', [], false),
                'cancel' => route('register.otp.cancel', [], false),
            ],
        ]);
    }

    public function verify(
        Request $request,
        ClientNotificationService $clientNotifications,
        AdminNotificationService $adminNotifications,
        SalesReferralService $referrals
    ): RedirectResponse {
        $pending = $this->otpService->pending($request);
        if (! $pending) {
            return redirect()->route('register');
        }

        $otp = $request->validate([
            'otp' => ['required', 'string', 'min:4', 'max:10'],
        ])['otp'];

        if (! $this->otpService->verify($request, $otp)) {
            return back()->withErrors(['otp' => 'Invalid or expired verification code.']);
        }

        $isSalesRep = $pending['account_type'] === AuthController::ACCOUNT_SALES_REP;

        // The address may have been taken while the code was on its way.
        if ($this->emailIsTaken((string) $pending['data']['email'], $isSalesRep)) {
            $this->otpService->forget($request);

            return redirect()->route('register', $isSalesRep ? ['as' => $pending['account_type']] : [])
                ->withInput(Arr::except($pending['data'], ['password', 'email']))
                ->withErrors(['email' => 'An account with this email already exists. Please sign in, continue with Google, or reset your password.']);
        }

        $this->otpService->forget($request);

        return $isSalesRep
            ? $this->createSalesRep($request, $pending['data'], $adminNotifications)
            : $this->createCustomer($request, $pending, $clientNotifications, $referrals);
    }

    public function resend(Request $request): RedirectResponse
    {
        $pending = $this->otpService->pending($request);
        if (! $pending) {
            return redirect()->route('register');
        }

        if ($this->otpService->sendLimitReached((string) $pending['data']['email'])) {
            return back()->withErrors(['otp' => 'Too many codes requested for this email. Please try again later.']);
        }

        if (! $this->otpService->sendCode($request) && ! LoginOtpService::isLocalDev()) {
            return back()->withErrors(['otp' => 'We could not send the verification email. Please try again shortly.']);
        }

        return back()->with('status', 'A new verification code has been sent to your email.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $pending = $this->otpService->pending($request);
        $this->otpService->forget($request);

        if (! $pending) {
            return redirect()->route('register');
        }

        $query = array_filter([
            'as' => $pending['account_type'] === AuthController::ACCOUNT_SALES_REP ? $pending['account_type'] : null,
            'redirect' => $pending['redirect'] ?? null,
        ]);

        return redirect()->route('register', $query)->withInput(Arr::except($pending['data'], ['password']));
    }

    private function emailIsTaken(string $email, bool $isSalesRep): bool
    {
        return User::where('email', $email)->exists()
            || (! $isSalesRep && Customer::where('email', $email)->exists());
    }

    private function createCustomer(
        Request $request,
        array $pending,
        ClientNotificationService $clientNotifications,
        SalesReferralService $referrals
    ): RedirectResponse {
        $data = $pending['data'];

        [$customer, $user] = DB::transaction(function () use ($data) {
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
                'currency' => $data['currency'],
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            return [$customer, $user];
        });

        $referrals->creditNewCustomer($customer, $request);

        Auth::login($user);
        $request->session()->regenerate();

        $clientNotifications->sendClientSignup($customer);

        return ($pending['redirect'] ?? null
            ? redirect($pending['redirect'])
            : redirect()->route('client.dashboard'))
            ->with('status', 'Welcome! Your account is ready.');
    }

    /**
     * A sales rep signs up to a pending account; an admin approves it before
     * the rep can sign in or share a referral link.
     */
    private function createSalesRep(Request $request, array $data, AdminNotificationService $adminNotifications): RedirectResponse
    {
        $rep = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => Role::SALES,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            return SalesRepresentative::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
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
}
