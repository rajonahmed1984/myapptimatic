<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * Admin sign-in always stops at an emailed one-time code. Tests that only care
 * about where a successful sign-in lands use this to get past that step.
 */
trait CompletesLoginOtp
{
    /**
     * When $response is the redirect to the OTP screen, confirm it was asked
     * for and submit a known code; otherwise hand $response straight back.
     */
    protected function completeLoginOtpIfRequired(TestResponse $response, User $user, string $portal): TestResponse
    {
        if (! $user->requiresLoginOtp($portal)) {
            return $response;
        }

        $response->assertRedirect(route('login.otp.show', ['portal' => $portal], false));
        $this->assertGuest('web');

        $code = '654321';
        $user->forceFill([
            'login_otp_code' => hash('sha256', $code),
            'login_otp_expires_at' => now()->addMinutes(10),
        ])->save();

        return $this->post(route('login.otp.verify'), ['otp' => $code]);
    }
}
