<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PasswordResetController extends Controller
{
    public function request(): InertiaResponse
    {
        return Inertia::render('Auth/ForgotPassword', $this->forgotPasswordProps(
            route('password.email', [], false),
            route('login', [], false),
        ));
    }

    public function requestAdmin(): InertiaResponse
    {
        return Inertia::render('Auth/ForgotPassword', $this->forgotPasswordProps(
            route('admin.password.email', [], false),
            route('admin.login', [], false),
        ));
    }

    public function email(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Validate reCAPTCHA
        $this->ensureRecaptcha($request, 'FORGOT_PASSWORD');

        // First try to find user directly by email
        $user = \App\Models\User::where('email', $request->email)->first();
        
        // If not found, try to find by customer email
        if (!$user) {
            $customer = \App\Models\Customer::where('email', $request->email)->first();
            if ($customer) {
                // Get the user associated with this customer
                $user = $customer->users()->where('role', 'client')->first();
            }
        }

        // If we found a user (either directly or via customer), send reset link
        if ($user) {
            try {
                $status = Password::sendResetLink([
                    'email' => $user->email,
                ]);
            } catch (\Throwable $exception) {
                Log::error('Failed to send password reset link', [
                    'email' => $user->email,
                    'exception' => $exception->getMessage(),
                ]);

                return back()
                    ->withInput($request->only('email'))
                    ->withErrors(['email' => 'Unable to send the reset link right now. Please try again later.']);
            }
        } else {
            $status = Password::INVALID_USER;
        }

        if ($status === \Illuminate\Support\Facades\Password::RESET_LINK_SENT) {
            return back()->with('status', __($status));
        }

        return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }

    public function emailAdmin(Request $request)
    {
        return $this->email($request);
    }

    public function resetForm(Request $request, string $token): InertiaResponse
    {
        return Inertia::render('Auth/ResetPassword', [
            'form' => [
                'token' => $token,
                'email' => old('email', (string) $request->query('email', '')),
            ],
            'routes' => [
                'submit' => route('password.update', [], false),
                'login' => route('login', [], false),
            ],
            'messages' => [
                'status' => session('status'),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                $user->forceFill([
                    'password' => $password,
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function forgotPasswordProps(string $emailRoute, string $loginRoute): array
    {
        return [
            'form' => [
                'email' => old('email', ''),
            ],
            'routes' => [
                'email' => $emailRoute,
                'login' => $loginRoute,
            ],
            'messages' => [
                'status' => session('status'),
                'throttled' => __('passwords.throttled'),
            ],
            'recaptcha' => [
                'enabled' => (bool) config('recaptcha.enabled') && is_string(config('recaptcha.site_key')) && config('recaptcha.site_key') !== '',
                'site_key' => (string) config('recaptcha.site_key', ''),
                'action' => 'FORGOT_PASSWORD',
            ],
        ];
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
            throw \Illuminate\Validation\ValidationException::withMessages([
                'recaptcha' => 'Please complete the reCAPTCHA.',
            ]);
        }

        $isValid = false;

        if (! empty($projectId) && ! empty($apiKey)) {
            $response = \Illuminate\Support\Facades\Http::timeout(8)->post(
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
            $response = \Illuminate\Support\Facades\Http::asForm()->timeout(8)->post('https://www.google.com/recaptcha/api/siteverify', [
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
            throw \Illuminate\Validation\ValidationException::withMessages([
                'recaptcha' => 'reCAPTCHA is not configured.',
            ]);
        }

        if (! $isValid) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'recaptcha' => 'reCAPTCHA verification failed. Please try again.',
            ]);
        }
    }
}
