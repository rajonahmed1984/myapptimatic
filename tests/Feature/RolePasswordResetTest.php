<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class RolePasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_forgot_password_creates_employee_token(): void
    {
        $user = User::factory()->create(['role' => Role::EMPLOYEE]);

        $response = $this->post(route('employee.password.email'), [
            'email' => $user->email,
        ]);

        $response->assertSessionHas('status');
        $this->assertTrue(DB::table('password_reset_tokens_employees')->where('email', $user->email)->exists());
    }

    public function test_sales_forgot_password_creates_sales_token(): void
    {
        $user = User::factory()->create(['role' => Role::SALES]);

        $response = $this->post(route('sales.password.email'), [
            'email' => $user->email,
        ]);

        $response->assertSessionHas('status');
        $this->assertTrue(DB::table('password_reset_tokens_sales')->where('email', $user->email)->exists());
    }

    public function test_support_forgot_password_creates_support_token(): void
    {
        $user = User::factory()->create(['role' => Role::SUPPORT]);

        $response = $this->post(route('support.password.email'), [
            'email' => $user->email,
        ]);

        $response->assertSessionHas('status');
        $this->assertTrue(DB::table('password_reset_tokens_support')->where('email', $user->email)->exists());
    }

    public function test_employee_reset_updates_password_and_redirects(): void
    {
        $user = User::factory()->create(['role' => Role::EMPLOYEE]);
        $token = Password::broker('employees')->createToken($user);

        $response = $this->post(route('employee.password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertRedirect(route('employee.login'));
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_sales_token_cannot_reset_employee_password(): void
    {
        $salesUser = User::factory()->create(['role' => Role::SALES]);
        $token = Password::broker('sales')->createToken($salesUser);

        $response = $this->post(route('employee.password.update'), [
            'token' => $token,
            'email' => $salesUser->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertFalse(Hash::check('new-password-123', $salesUser->fresh()->password));
    }
}
