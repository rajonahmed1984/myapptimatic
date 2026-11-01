<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\SalesRepresentative;
use App\Models\User;
use App\Services\AuthFresh\RegistrationOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegistrationOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.default' => 'array',
            'recaptcha.enabled' => false,
            'recaptcha.site_key' => null,
            'recaptcha.secret_key' => null,
        ]);
    }

    #[Test]
    public function a_customer_account_is_only_created_after_the_emailed_code_is_entered(): void
    {
        $this->post(route('register.store'), $this->customerPayload('new@example.com'))
            ->assertRedirect(route('register.otp.show'));

        $this->assertSame(0, User::where('email', 'new@example.com')->count());
        $this->assertSame(0, Customer::where('email', 'new@example.com')->count());
        $this->assertGuest('web');

        $this->get(route('register.otp.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/VerifyOtp')->where('heading', 'Verify your email'));

        $this->post(route('register.otp.verify'), ['otp' => $this->emailedCode('new@example.com')])
            ->assertRedirect(route('client.dashboard'));

        $user = User::where('email', 'new@example.com')->sole();
        $this->assertSame(Role::CLIENT, $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('secret-pass', $user->password));
        $this->assertSame('+8801711000000', $user->customer->phone);
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertNull(session(RegistrationOtpService::SESSION_KEY));
    }

    #[Test]
    public function a_sales_rep_also_verifies_the_email_and_then_waits_for_approval(): void
    {
        $this->post(route('register.store'), [
            'account_type' => 'sales_rep',
            'name' => 'Rep',
            'email' => 'rep@example.com',
            'phone_country' => '+880',
            'phone' => '01711000000',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ])->assertRedirect(route('register.otp.show'));

        $this->assertSame(0, User::where('email', 'rep@example.com')->count());

        $this->post(route('register.otp.verify'), ['otp' => $this->emailedCode('rep@example.com')])
            ->assertRedirect(route('login'));

        $user = User::where('email', 'rep@example.com')->sole();
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('pending', SalesRepresentative::where('user_id', $user->id)->sole()->status);
        $this->assertGuest('sales');
    }

    #[Test]
    public function a_wrong_code_creates_nothing_and_repeated_guesses_kill_the_code(): void
    {
        $this->post(route('register.store'), $this->customerPayload('guess@example.com'));
        $code = $this->emailedCode('guess@example.com');
        $wrong = $code === '111111' ? '222222' : '111111';

        for ($i = 0; $i < RegistrationOtpService::MAX_ATTEMPTS; $i++) {
            $this->post(route('register.otp.verify'), ['otp' => $wrong])->assertSessionHasErrors('otp');
        }

        $this->post(route('register.otp.verify'), ['otp' => $code])->assertSessionHasErrors('otp');
        $this->assertSame(0, User::where('email', 'guess@example.com')->count());
    }

    #[Test]
    public function an_expired_code_is_rejected(): void
    {
        $this->post(route('register.store'), $this->customerPayload('late@example.com'));
        $code = $this->emailedCode('late@example.com');

        $this->travel(11)->minutes();

        $this->post(route('register.otp.verify'), ['otp' => $code])->assertSessionHasErrors('otp');
        $this->assertSame(0, User::where('email', 'late@example.com')->count());
    }

    #[Test]
    public function resending_replaces_the_old_code(): void
    {
        $this->post(route('register.store'), $this->customerPayload('again@example.com'));
        $oldCode = $this->emailedCode('again@example.com');

        $this->post(route('register.otp.resend'))->assertSessionHas('status');
        $newCode = $this->emailedCode('again@example.com');

        if ($oldCode !== $newCode) {
            $this->post(route('register.otp.verify'), ['otp' => $oldCode])->assertSessionHasErrors('otp');
        }

        $this->post(route('register.otp.verify'), ['otp' => $newCode])->assertRedirect(route('client.dashboard'));
        $this->assertSame(1, User::where('email', 'again@example.com')->count());
    }

    #[Test]
    public function too_many_codes_for_one_email_are_refused(): void
    {
        for ($i = 0; $i < RegistrationOtpService::MAX_SENDS_PER_HOUR; $i++) {
            $this->post(route('register.store'), $this->customerPayload('spam@example.com'))
                ->assertRedirect(route('register.otp.show'));
        }

        $this->post(route('register.store'), $this->customerPayload('spam@example.com'))
            ->assertSessionHasErrors('email');

        $this->assertCount(RegistrationOtpService::MAX_SENDS_PER_HOUR, $this->messagesTo('spam@example.com'));
    }

    #[Test]
    public function an_email_taken_while_waiting_for_the_code_stops_the_sign_up(): void
    {
        $this->post(route('register.store'), $this->customerPayload('race@example.com'));
        $code = $this->emailedCode('race@example.com');

        User::factory()->create(['email' => 'race@example.com']);

        $this->post(route('register.otp.verify'), ['otp' => $code])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('email');

        $this->assertSame(0, Customer::where('email', 'race@example.com')->count());
        $this->assertGuest('web');
    }

    #[Test]
    public function the_session_never_holds_the_plain_password_and_the_verify_page_needs_a_sign_up(): void
    {
        $this->get(route('register.otp.show'))->assertRedirect(route('register'));

        $this->post(route('register.store'), $this->customerPayload('safe@example.com'));

        $this->assertStringNotContainsString('secret-pass', json_encode(session(RegistrationOtpService::SESSION_KEY)));
    }

    #[Test]
    public function going_back_keeps_the_details_except_the_password(): void
    {
        $this->post(route('register.store'), $this->customerPayload('back@example.com'));

        $this->get(route('register.otp.cancel'))
            ->assertRedirect(route('register'))
            ->assertSessionHasInput('email', 'back@example.com')
            ->assertSessionMissing(RegistrationOtpService::SESSION_KEY);

        $this->assertArrayNotHasKey('password', session()->getOldInput());
    }

    private function customerPayload(string $email): array
    {
        return [
            'name' => 'New Customer',
            'email' => $email,
            'phone_country' => '+880',
            'phone' => '01711000000',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ];
    }

    private function messagesTo(string $email): array
    {
        return collect(Mail::mailer('array')->getSymfonyTransport()->messages())
            ->filter(fn ($sent) => collect($sent->getOriginalMessage()->getTo())->contains(fn ($address) => $address->getAddress() === $email))
            ->values()
            ->all();
    }

    private function emailedCode(string $email): string
    {
        $messages = $this->messagesTo($email);
        $this->assertNotEmpty($messages, "No code was emailed to {$email}.");

        preg_match('/(\d{6})$/', end($messages)->getOriginalMessage()->getSubject(), $matches);

        return $matches[1];
    }
}
