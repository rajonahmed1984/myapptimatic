<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Http\Middleware\TrackAuthenticatedUserActivity;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecaptchaLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_login_works_when_recaptcha_disabled(): void
    {
        config([
            'recaptcha.enabled' => false,
            'recaptcha.site_key' => null,
            'recaptcha.secret_key' => null,
        ]);

        $user = User::factory()->create([
            'role' => Role::CLIENT,
            'password' => Hash::make('secret1234'),
        ]);

        $response = $this->post(route('login.attempt'), [
            'email' => $user->email,
            'password' => 'secret1234',
        ]);

        $response->assertRedirect(route('client.dashboard'));
    }

    public function test_employee_login_requires_recaptcha_when_enabled(): void
    {
        config([
            'recaptcha.enabled' => true,
            'recaptcha.site_key' => 'site-key',
            'recaptcha.secret_key' => 'secret-key',
        ]);

        $user = User::factory()->create([
            'role' => Role::EMPLOYEE,
            'password' => Hash::make('secret1234'),
        ]);

        Employee::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => 'active',
        ]);

        $response = $this->withoutMiddleware([
            TrackAuthenticatedUserActivity::class,
        ])->post(route('employee.login.attempt'), [
            'email' => $user->email,
            'password' => 'secret1234',
        ]);

        $response->assertSessionHasErrors('recaptcha');
    }

    public function test_employee_login_rejects_invalid_recaptcha(): void
    {
        config([
            'recaptcha.enabled' => true,
            'recaptcha.site_key' => 'site-key',
            'recaptcha.secret_key' => 'secret-key',
        ]);

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => false,
            ], 200),
        ]);

        $user = User::factory()->create([
            'role' => Role::EMPLOYEE,
            'password' => Hash::make('secret1234'),
        ]);

        Employee::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => 'active',
        ]);

        $response = $this->withoutMiddleware([
            TrackAuthenticatedUserActivity::class,
        ])->post(route('employee.login.attempt'), [
            'email' => $user->email,
            'password' => 'secret1234',
            'g-recaptcha-response' => 'invalid-token',
        ]);

        $response->assertSessionHasErrors('recaptcha');
    }

    public function test_employee_login_accepts_valid_recaptcha(): void
    {
        config([
            'recaptcha.enabled' => true,
            'recaptcha.site_key' => 'site-key',
            'recaptcha.secret_key' => 'secret-key',
        ]);

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
            ], 200),
        ]);

        $user = User::factory()->create([
            'role' => Role::EMPLOYEE,
            'password' => Hash::make('secret1234'),
        ]);

        Employee::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => 'active',
        ]);

        $response = $this->withoutMiddleware([
            TrackAuthenticatedUserActivity::class,
        ])->post(route('employee.login.attempt'), [
            'email' => $user->email,
            'password' => 'secret1234',
            'g-recaptcha-response' => 'valid-token',
        ]);

        $response->assertRedirect(route('employee.dashboard'));
    }
}
