<?php

namespace Tests\Concerns;

use App\Services\AuthFresh\RegistrationOtpService;
use Illuminate\Testing\TestResponse;

/**
 * Registration stops at an emailed code. Tests that only care about what the
 * finished sign-up creates use this to get past that step.
 */
trait CompletesRegistrationOtp
{
    protected function completeRegistrationOtp(): TestResponse
    {
        $code = '654321';
        session()->put(RegistrationOtpService::SESSION_KEY.'.otp_hash', hash('sha256', $code));

        return $this->post(route('register.otp.verify'), ['otp' => $code]);
    }
}
