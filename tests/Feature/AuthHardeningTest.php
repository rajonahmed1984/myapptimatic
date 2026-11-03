<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\User;
use App\Services\AuthFresh\LoginOtpService;
use App\Support\AuthFresh\Portal;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['recaptcha.enabled' => false, 'recaptcha.site_key' => null]);
    }

    private function goLive(bool $debug): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.env' => 'production', 'app.debug' => $debug]);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function fakeGoogle(array $profile): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);
        session(['oauth_state_google' => 'state-123']);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'token']),
            'https://www.googleapis.com/oauth2/v3/userinfo' => Http::response($profile),
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::MASTER_ADMIN, 'email' => 'admin@example.com', 'password' => 'secret-password']);
    }

    public function test_sandbox_is_closed_on_a_live_server_even_with_debug_on(): void
    {
        $admin = $this->admin();
        $this->goLive(debug: true);

        $this->get(route('auth.sandbox', ['provider' => 'google']))->assertForbidden();
        $this->post(route('auth.sandbox.login', ['provider' => 'google']), ['email' => $admin->email, 'portal' => 'admin'])->assertForbidden();
        $this->assertGuest('web');

        config(['services.google.client_id' => null]);
        $this->get('/auth/google/redirect')->assertRedirect('/login');
    }

    public function test_sandbox_is_closed_in_a_local_env_without_debug(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config(['app.env' => 'local', 'app.debug' => false]);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->get(route('auth.sandbox', ['provider' => 'google']))->assertForbidden();
    }

    public function test_sandbox_cannot_sign_in_an_admin(): void
    {
        $admin = $this->admin();

        foreach (['admin', 'web', 'employee'] as $portal) {
            $this->post(route('auth.sandbox.login', ['provider' => 'google']), ['email' => $admin->email, 'portal' => $portal])
                ->assertSessionHas('social_error');
            $this->assertGuest('web');
            $this->assertGuest('employee');
        }
    }

    public function test_google_cannot_skip_an_admins_emailed_code(): void
    {
        $admin = $this->admin();
        $this->fakeGoogle(['email' => $admin->email, 'email_verified' => true, 'name' => 'Admin']);

        $this->get('/auth/google/callback?state=state-123&code=abc')->assertRedirect('/login');

        $this->assertGuest('web');
    }

    public function test_google_ignores_a_portal_planted_in_the_session(): void
    {
        $admin = $this->admin();
        $this->fakeGoogle(['email' => $admin->email, 'email_verified' => true]);
        session(['social_auth_portal' => 'admin']);

        $this->get('/auth/google/callback?state=state-123&code=abc')->assertRedirect('/login');

        $this->assertGuest('web');
    }

    public function test_google_is_refused_on_staff_portals(): void
    {
        config(['services.google.client_id' => 'id']);

        $this->get('/auth/google/redirect?portal=admin')
            ->assertRedirect('/admin/login')
            ->assertSessionHas('social_error');
    }

    public function test_google_needs_an_explicitly_verified_email(): void
    {
        $this->fakeGoogle(['email' => 'nobody@example.com', 'name' => 'No Flag']);

        $this->get('/auth/google/callback?state=state-123&code=abc')
            ->assertSessionHas('social_error', 'Your Google email address is not verified.');

        $this->assertGuest('web');
        $this->assertDatabaseMissing('users', ['email' => 'nobody@example.com']);
    }

    public function test_google_failure_does_not_show_the_internal_error(): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);
        session(['oauth_state_google' => 'state-123']);
        Http::fake(fn () => throw new \RuntimeException('secret internal detail'));

        $this->get('/auth/google/callback?state=state-123&code=abc')
            ->assertSessionHas('social_error', 'Google sign-in did not complete. Please try again.');
    }

    public function test_the_stored_hash_is_not_accepted_as_a_code(): void
    {
        $admin = $this->admin();
        $this->post(route('admin.login.attempt'), ['email' => $admin->email, 'password' => 'secret-password']);
        $admin->update(['login_otp_code' => hash('sha256', '654321'), 'login_otp_expires_at' => now()->addMinutes(10)]);

        $this->post(route('login.otp.verify'), ['otp' => substr(hash('sha256', '654321'), 0, 10)])->assertSessionHasErrors('otp');

        $this->assertFalse(app(LoginOtpService::class)->verify($admin->refresh(), hash('sha256', '654321')));
        $this->assertGuest('web');
    }

    public function test_fixed_local_code_needs_a_local_env_and_debug(): void
    {
        $admin = $this->admin();
        $service = app(LoginOtpService::class);

        config(['app.env' => 'production', 'app.debug' => true]);
        $this->assertFalse($service->verify($admin, LoginOtpService::LOCAL_TEMP_OTP));

        config(['app.env' => 'local', 'app.debug' => false]);
        $this->assertFalse($service->verify($admin, LoginOtpService::LOCAL_TEMP_OTP));

        config(['app.env' => 'local', 'app.debug' => true]);
        $this->assertTrue($service->verify($admin, LoginOtpService::LOCAL_TEMP_OTP));
    }

    public function test_code_is_refused_once_the_account_loses_access(): void
    {
        $admin = $this->admin();
        $this->post(route('admin.login.attempt'), ['email' => $admin->email, 'password' => 'secret-password']);
        $admin->update(['role' => Role::CLIENT, 'login_otp_code' => hash('sha256', '654321'), 'login_otp_expires_at' => now()->addMinutes(10)]);

        $this->post(route('login.otp.verify'), ['otp' => '654321'])->assertRedirect('/admin/login');

        $this->assertGuest('web');
    }

    public function test_safe_redirect_only_allows_paths_on_this_site(): void
    {
        foreach (['//evil.example', '/'.chr(92).'evil.example', "/\t/evil.example", "/\n/evil.example", 'https://evil.example', 'evil.example', '', null, ['/x']] as $target) {
            $this->assertNull(Portal::safeRedirect($target));
        }

        $this->assertSame('/client/invoices/5?x=1', Portal::safeRedirect('/client/invoices/5?x=1'));
    }

    public function test_changing_the_forwarded_ip_does_not_escape_the_limit_on_one_account(): void
    {
        $customer = Customer::create(['name' => 'Acme', 'email' => 'client@example.com']);
        $user = User::factory()->create(['role' => Role::CLIENT, 'customer_id' => $customer->id, 'password' => 'secret-password']);

        foreach (range(1, 20) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->post(route('login.attempt'), ['email' => $user->email, 'password' => 'wrong-password'])
                ->assertSessionHasErrors('email');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])
            ->post(route('login.attempt'), ['email' => $user->email, 'password' => 'secret-password'])
            ->assertSessionHasErrors(['email' => 'Too many login attempts. Please try again in 60 seconds.']);

        $this->assertGuest('web');
    }

    public function test_successful_login_resets_the_attempt_count(): void
    {
        $customer = Customer::create(['name' => 'Acme', 'email' => 'client@example.com']);
        $user = User::factory()->create(['role' => Role::CLIENT, 'customer_id' => $customer->id, 'password' => 'secret-password']);

        foreach (range(1, 4) as $ignored) {
            $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'secret-password']);
        $this->assertAuthenticatedAs($user, 'web');
        $this->post('/logout');

        foreach (range(1, 4) as $ignored) {
            $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'wrong-password'])
                ->assertSessionDoesntHaveErrors(['email' => 'Too many login attempts. Please try again in 60 seconds.']);
        }
    }

    public function test_forgot_password_answers_the_same_for_known_and_unknown_emails(): void
    {
        $user = User::factory()->create(['role' => Role::CLIENT]);

        $known = $this->from('/forgot-password')->post(route('password.email'), ['email' => $user->email]);
        $unknown = $this->from('/forgot-password')->post(route('password.email'), ['email' => 'nobody@example.com']);

        $known->assertSessionHasNoErrors();
        $unknown->assertSessionHasNoErrors();
        $unknown->assertSessionHas('status', 'If your email is in our system, we have sent a reset link.');
    }

    public function test_forgot_password_is_throttled(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->post(route('password.email'), ['email' => 'nobody@example.com']);
        }

        $this->post(route('password.email'), ['email' => 'nobody@example.com'])->assertStatus(429);
    }
}
