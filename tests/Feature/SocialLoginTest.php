<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Enums\Role;
use App\Support\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('recaptcha.enabled', false);
    }

    public function test_redirect_route_redirects_to_sandbox_when_credentials_are_missing_in_local(): void
    {
        config()->set('services.google.client_id', null);

        $response = $this->get('/auth/google/redirect?portal=web');

        $response->assertRedirect(route('auth.sandbox', ['provider' => 'google', 'portal' => 'web']));
    }

    public function test_redirect_route_redirects_to_login_with_flash_error_when_credentials_are_missing_in_production(): void
    {
        $this->app->detectEnvironment(fn() => 'production');
        config()->set('app.debug', false);
        config()->set('services.google.client_id', null);

        $response = $this->get('/auth/google/redirect?portal=web');

        $response->assertRedirect('/login');
        $response->assertSessionHas('social_error', 'Google login is currently unavailable.');
    }

    public function test_redirect_route_redirects_to_google_when_credentials_exist(): void
    {
        config()->set('services.google.client_id', 'google-client-id');

        $response = $this->get('/auth/google/redirect?portal=web');

        $response->assertRedirectContains('https://accounts.google.com/o/oauth2/v2/auth');
        $response->assertRedirectContains('client_id=google-client-id');
        $this->assertNotEmpty(session('oauth_state_google'));
    }

    public function test_sandbox_page_renders_successfully(): void
    {
        $response = $this->get(route('auth.sandbox', ['provider' => 'google', 'portal' => 'web']));

        $response->assertOk()
            ->assertSee('Auth\\/SocialSandbox', false);
    }

    public function test_sandbox_login_logs_in_existing_user(): void
    {
        $customer = Customer::create([
            'name' => 'Existing Customer',
            'email' => 'existing@example.com',
            'status' => 'active',
        ]);

        $user = User::create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'password' => 'secret123',
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
            'currency' => Currency::DEFAULT,
            'status' => 'active',
        ]);

        $response = $this->post(route('auth.sandbox.login', ['provider' => 'google']), [
            'email' => 'existing@example.com',
            'name' => 'Existing User',
            'portal' => 'web',
        ]);

        $response->assertRedirect(route('client.dashboard'));
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_sandbox_login_registers_and_logs_in_new_user(): void
    {
        $this->assertDatabaseMissing('users', ['email' => 'newuser@example.com']);
        $this->assertDatabaseMissing('customers', ['email' => 'newuser@example.com']);

        $response = $this->post(route('auth.sandbox.login', ['provider' => 'google']), [
            'email' => 'newuser@example.com',
            'name' => 'New User',
            'portal' => 'web',
        ]);

        $response->assertRedirect(route('client.dashboard'));
        $this->assertAuthenticated('web');

        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('New User', $user->name);
        $this->assertSame(Role::CLIENT, $user->role);

        $customer = Customer::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($customer);
        $this->assertSame('New User', $customer->name);
        $this->assertSame($customer->id, $user->customer_id);
    }

    public function test_oauth_callback_google_authenticates_successfully_with_mocked_http(): void
    {
        config()->set('services.google.client_id', 'google-client-id');
        config()->set('services.google.client_secret', 'google-client-secret');

        // Seed session state
        session(['oauth_state_google' => 'state-123']);
        session(['social_auth_portal' => 'web']);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'mock-access-token',
            ], 200),
            'https://www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'email' => 'googleuser@example.com',
                'name' => 'Google User',
            ], 200),
        ]);

        $response = $this->get('/auth/google/callback?state=state-123&code=auth-code');

        $response->assertRedirect(route('client.dashboard'));
        $this->assertAuthenticated('web');

        $user = User::where('email', 'googleuser@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('Google User', $user->name);
    }
}
