<?php

namespace App\Services\AuthFresh;

use App\Enums\MailCategory;
use App\Services\Mail\MailSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Holds a sign-up in the session until its email address is proven, so nothing
 * is created for an address the person does not control.
 */
class RegistrationOtpService
{
    public const SESSION_KEY = 'register.pending';
    public const MAX_ATTEMPTS = 5;
    public const MAX_SENDS_PER_HOUR = 5;

    public function __construct(
        private readonly MailSender $mailSender
    ) {
    }

    /**
     * Keep the validated sign-up and email its code. False means the code could not be delivered.
     */
    public function start(Request $request, string $accountType, array $data, ?string $redirect): bool
    {
        $data['password'] = Hash::make($data['password']);

        $request->session()->put(self::SESSION_KEY, [
            'account_type' => $accountType,
            'data' => $data,
            'redirect' => $redirect,
        ]);

        return $this->sendCode($request);
    }

    public function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        return is_array($pending) && ! empty($pending['data']['email']) ? $pending : null;
    }

    public function forget(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    public function sendLimitReached(string $email): bool
    {
        return RateLimiter::tooManyAttempts($this->sendKey($email), self::MAX_SENDS_PER_HOUR);
    }

    /**
     * Replace the session's code with a new one and email it.
     */
    public function sendCode(Request $request): bool
    {
        $pending = $this->pending($request);
        if (! $pending) {
            return false;
        }

        $email = (string) $pending['data']['email'];
        $code = (string) random_int(100000, 999999);

        $pending['otp_hash'] = hash('sha256', $code);
        $pending['otp_expires_at'] = now()->addMinutes(LoginOtpService::OTP_EXPIRY_MINUTES)->getTimestamp();
        $pending['attempts'] = 0;
        $request->session()->put(self::SESSION_KEY, $pending);

        RateLimiter::hit($this->sendKey($email), 3600);

        return $this->sendEmail($email, $code);
    }

    /**
     * A code stops working once it expires or after too many wrong guesses.
     */
    public function verify(Request $request, string $submittedOtp): bool
    {
        $pending = $this->pending($request);
        if (! $pending) {
            return false;
        }

        $submittedOtp = trim($submittedOtp);

        if (LoginOtpService::isLocalDev() && $submittedOtp === LoginOtpService::LOCAL_TEMP_OTP) {
            return true;
        }

        $attempts = (int) ($pending['attempts'] ?? 0);
        $isLive = ! empty($pending['otp_hash'])
            && $attempts < self::MAX_ATTEMPTS
            && now()->getTimestamp() <= (int) ($pending['otp_expires_at'] ?? 0);

        if ($isLive && hash_equals((string) $pending['otp_hash'], hash('sha256', $submittedOtp))) {
            return true;
        }

        $pending['attempts'] = $attempts + 1;
        $request->session()->put(self::SESSION_KEY, $pending);

        return false;
    }

    private function sendKey(string $email): string
    {
        return 'register-otp-send:'.sha1(strtolower($email));
    }

    private function sendEmail(string $email, string $code): bool
    {
        $appName = (string) config('app.name', 'MyApptimatic');
        $minutes = LoginOtpService::OTP_EXPIRY_MINUTES;
        $safeAppName = e($appName);

        $html = "
            <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 12px; background: #ffffff;'>
                <div style='text-align: center; margin-bottom: 20px;'>
                    <h2 style='color: #0f172a; margin: 0 0 6px;'>Verify your email</h2>
                    <p style='color: #64748b; font-size: 14px; margin: 0;'>Finish creating your {$safeAppName} account</p>
                </div>
                <div style='background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 18px; text-align: center; margin: 20px 0;'>
                    <span style='font-size: 32px; font-weight: bold; letter-spacing: 6px; color: #0284c7;'>{$code}</span>
                </div>
                <p style='font-size: 13px; color: #475569; line-height: 1.5;'>
                    Enter this 6-digit code to complete your registration. It expires in <b>{$minutes} minutes</b>.
                </p>
                <p style='font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 14px; margin-top: 20px;'>
                    If you did not try to register, you can ignore this email. No account has been created.
                </p>
            </div>
        ";

        $text = "Your {$appName} email verification code is: {$code}\n\nIt expires in {$minutes} minutes.\nIf you did not try to register, you can ignore this email.";

        try {
            $this->mailSender->sendHtmlText(
                MailCategory::SYSTEM,
                $email,
                "Your {$appName} email verification code: {$code}",
                $html,
                $text
            );

            return true;
        } catch (Throwable $e) {
            Log::warning("Failed to send registration OTP email to {$email}: ".$e->getMessage());

            return false;
        }
    }
}
