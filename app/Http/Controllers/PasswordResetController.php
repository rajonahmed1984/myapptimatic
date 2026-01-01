<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController extends Controller
{
    public function request()
    {
        return view('auth.forgot-password');
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
            $status = \Illuminate\Support\Facades\Password::sendResetLink([
                'email' => $user->email
            ]);
        } else {
            $status = \Illuminate\Support\Facades\Password::INVALID_USER;
        }

        if ($status === \Illuminate\Support\Facades\Password::RESET_LINK_SENT) {
            return back()->with('status', __($status));
        }

        return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }

    public function resetForm(string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
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
