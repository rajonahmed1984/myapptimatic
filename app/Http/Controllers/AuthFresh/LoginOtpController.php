<?php

namespace App\Http\Controllers\AuthFresh;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthFresh\LoginOtpService;
use App\Services\AuthFresh\LoginService;
use App\Support\AuthFresh\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class LoginOtpController extends Controller
{
    public function __construct(
        private readonly LoginOtpService $otpService,
        private readonly LoginService $loginService
    ) {
    }

    public function show(Request $request): InertiaResponse|RedirectResponse
    {
        $pending = $request->session()->get('login.pending_otp');
        if (! is_array($pending) || empty($pending['user_id'])) {
            $portal = Portal::fromRequest($request);
            return redirect(Portal::portalLoginUrl($portal));
        }

        $user = User::find($pending['user_id']);
        if (! $user) {
            $request->session()->forget('login.pending_otp');
            return redirect(route('login', [], false));
        }

        $portal = (string) ($pending['portal'] ?? 'web');

        return Inertia::render('Auth/VerifyOtp', [
            'pageTitle' => 'Security Verification',
            'portal' => $portal,
            'email' => $this->otpService->maskEmail($user->email),
            'isLocal' => LoginOtpService::isLocalDev(),
            'localTempOtp' => LoginOtpService::isLocalDev() ? LoginOtpService::LOCAL_TEMP_OTP : null,
            'routes' => [
                'submit' => route('login.otp.verify', [], false),
                'resend' => route('login.otp.resend', [], false),
                'cancel' => route('login.otp.cancel', [], false),
            ],
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('login.pending_otp');
        if (! is_array($pending) || empty($pending['user_id'])) {
            return redirect(route('login', [], false));
        }

        $data = $request->validate([
            'otp' => ['required', 'string', 'min:4', 'max:10'],
        ]);

        $user = User::find($pending['user_id']);
        if (! $user) {
            $request->session()->forget('login.pending_otp');
            return redirect(route('login', [], false));
        }

        $portal = (string) ($pending['portal'] ?? 'web');
        $throttleKey = 'verify-otp:' . $user->id;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors([
                'otp' => "Too many incorrect attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        if (! $this->otpService->verify($user, $data['otp'])) {
            RateLimiter::hit($throttleKey, 120);
            return back()->withErrors([
                'otp' => 'Invalid or expired verification code.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $guard = Portal::guard($portal);
        $remember = (bool) ($pending['remember'] ?? false);
        $safeRedirect = $pending['redirect'] ?? null;

        Auth::guard($guard)->login($user, $remember);

        $request->session()->forget('login.pending_otp');
        $request->session()->regenerate();
        $request->session()->forget('impersonator_id');
        Portal::setPortal($request, $portal);
        RateLimiter::clear(LoginService::limiterKey($request, $portal, $user->email));

        $targetUrl = $safeRedirect ?: $this->loginService->defaultRedirectUrlFor($portal, $user);

        return redirect()->intended($targetUrl);
    }

    public function resend(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('login.pending_otp');
        if (! is_array($pending) || empty($pending['user_id'])) {
            return redirect(route('login', [], false));
        }

        $user = User::find($pending['user_id']);
        if (! $user) {
            $request->session()->forget('login.pending_otp');
            return redirect(route('login', [], false));
        }

        $throttleKey = 'resend-otp:' . $user->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 2)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors([
                'otp' => "Please wait {$seconds} seconds before requesting a new code.",
            ]);
        }

        RateLimiter::hit($throttleKey, 60);

        $portal = (string) ($pending['portal'] ?? 'web');
        $this->otpService->generateAndSend($user, $portal);

        return back()->with('status', 'A new verification code has been sent to your email.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('login.pending_otp');
        $portal = (string) ($pending['portal'] ?? 'web');

        $request->session()->forget('login.pending_otp');

        return redirect(Portal::portalLoginUrl($portal));
    }
}
