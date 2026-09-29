<?php

namespace App\Services\AuthFresh;

use App\Enums\MailCategory;
use App\Models\User;
use App\Services\Mail\MailSender;
use App\Support\SystemLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class LoginOtpService
{
    public const LOCAL_TEMP_OTP = '123456';
    public const OTP_EXPIRY_MINUTES = 10;

    public function __construct(
        private readonly MailSender $mailSender
    ) {
    }

    public static function isLocalDev(): bool
    {
        return app()->environment('local') || config('app.env') === 'local';
    }

    /**
     * Generate an OTP code, save it to the user, and email it.
     */
    public function generateAndSend(User $user, string $portal = 'web'): string
    {
        // 6-digit numeric OTP code
        $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        $user->update([
            'login_otp_code' => hash('sha256', $code),
            'login_otp_expires_at' => Carbon::now()->addMinutes(self::OTP_EXPIRY_MINUTES),
        ]);

        $this->sendOtpEmail($user, $code, $portal);

        SystemLogger::write('admin', 'Login OTP generated.', [
            'user_id' => $user->id,
            'email' => $user->email,
            'portal' => $portal,
            'expires_at' => $user->login_otp_expires_at?->toDateTimeString(),
        ], $user->id);

        return $code;
    }

    /**
     * Verify the supplied OTP code.
     */
    public function verify(User $user, string $submittedOtp): bool
    {
        $submittedOtp = trim($submittedOtp);

        // 1. In local development, always accept the temporary dev OTP
        if (self::isLocalDev() && $submittedOtp === self::LOCAL_TEMP_OTP) {
            $this->clearOtp($user);
            return true;
        }

        // 2. Validate against stored code and expiration
        if (empty($user->login_otp_code) || empty($user->login_otp_expires_at)) {
            return false;
        }

        if (Carbon::now()->isAfter($user->login_otp_expires_at)) {
            return false;
        }

        $expectedHash = (string) $user->login_otp_code;
        $submittedHash = hash('sha256', $submittedOtp);

        if (hash_equals($expectedHash, $submittedHash) || hash_equals($expectedHash, $submittedOtp)) {
            $this->clearOtp($user);
            return true;
        }

        return false;
    }

    public function clearOtp(User $user): void
    {
        $user->update([
            'login_otp_code' => null,
            'login_otp_expires_at' => null,
        ]);
    }

    public function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return $email;
        }

        $name = $parts[0];
        $domain = $parts[1];

        $len = strlen($name);
        if ($len <= 2) {
            $maskedName = substr($name, 0, 1) . '*';
        } else {
            $maskedName = substr($name, 0, 2) . str_repeat('*', max(1, $len - 3)) . substr($name, -1);
        }

        return $maskedName . '@' . $domain;
    }

    private function sendOtpEmail(User $user, string $code, string $portal): void
    {
        $portalName = match ($portal) {
            'admin' => 'Admin Portal',
            'employee' => 'Employee Portal',
            'sales' => 'Sales Portal',
            'support' => 'Support Portal',
            default => 'MyApptimatic',
        };

        $subject = "Your {$portalName} Login Verification Code: {$code}";

        $html = "
            <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 12px; background: #ffffff;'>
                <div style='text-align: center; margin-bottom: 20px;'>
                    <h2 style='color: #0f172a; margin: 0 0 6px;'>Security Verification</h2>
                    <p style='color: #64748b; font-size: 14px; margin: 0;'>Sign-in attempt for {$portalName}</p>
                </div>
                <div style='background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 18px; text-align: center; margin: 20px 0;'>
                    <span style='font-size: 32px; font-weight: bold; letter-spacing: 6px; color: #0284c7;'>{$code}</span>
                </div>
                <p style='font-size: 13px; color: #475569; line-height: 1.5;'>
                    Please enter this 6-digit code to complete your login. This code will expire in <b>" . self::OTP_EXPIRY_MINUTES . " minutes</b>.
                </p>
                <p style='font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 14px; margin-top: 20px;'>
                    If you did not initiate this login request, please change your password immediately.
                </p>
            </div>
        ";

        $text = "Your {$portalName} Login Verification Code is: {$code}\n\nThis code will expire in " . self::OTP_EXPIRY_MINUTES . " minutes.\nIf you did not initiate this request, please contact support.";

        try {
            $this->mailSender->sendHtmlText(
                MailCategory::SYSTEM,
                $user->email,
                $subject,
                $html,
                $text
            );
        } catch (Throwable $e) {
            // In local or offline dev, log the error without blocking local login
            Log::warning("Failed to send login OTP email to {$user->email}: " . $e->getMessage());
        }
    }
}
