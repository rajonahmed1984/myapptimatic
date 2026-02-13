<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginRedirectBehaviorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'recaptcha.enabled' => false,
            'recaptcha.site_key' => null,
            'recaptcha.secret_key' => null,
            'recaptcha.project_id' => null,
            'recaptcha.api_key' => null,
        ]);
    }

    public function test_client_login_with_wrong_password_redirects_back_with_errors(): void
    {
        $user = User::factory()->create([
            'role' => Role::CLIENT,
            'password' => 'correct-password',
        ]);

        $response = $this->from(route('login'))->post(route('login.attempt'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
    }

    public function test_employee_login_with_wrong_password_redirects_back_with_errors(): void
    {
        $user = User::factory()->create([
            'role' => Role::EMPLOYEE,
            'password' => 'correct-password',
        ]);

        $response = $this->from(route('employee.login'))->post(route('employee.login.attempt'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('employee.login'));
        $response->assertSessionHasErrors('email');
    }

    public function test_sales_login_with_wrong_password_redirects_back_with_errors(): void
    {
        $user = User::factory()->create([
            'role' => Role::SALES,
            'password' => 'correct-password',
        ]);

        $response = $this->from(route('sales.login'))->post(route('sales.login.attempt'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('sales.login'));
        $response->assertSessionHasErrors('email');
    }

    public function test_support_login_with_wrong_password_redirects_back_with_errors(): void
    {
        $user = User::factory()->create([
            'role' => Role::SUPPORT,
            'password' => 'correct-password',
        ]);

        $response = $this->from(route('support.login'))->post(route('support.login.attempt'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('support.login'));
        $response->assertSessionHasErrors('email');
    }

    public function test_admin_login_with_wrong_password_redirects_back_with_errors(): void
    {
        $user = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
            'password' => 'correct-password',
        ]);

        $response = $this->from(route('admin.login'))->post(route('admin.login.attempt'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors('email');
    }

    public function test_guest_accessing_admin_route_redirects_to_admin_login(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_guest_accessing_employee_route_redirects_to_employee_login(): void
    {
        $response = $this->get(route('employee.dashboard'));

        $response->assertRedirect(route('employee.login'));
    }

    public function test_guest_accessing_sales_route_redirects_to_sales_login(): void
    {
        $response = $this->get(route('rep.dashboard'));

        $response->assertRedirect(route('sales.login'));
    }

    public function test_sales_login_csrf_mismatch_redirects_to_sales_login(): void
    {
        $response = $this->withMiddleware()->post(route('sales.login.attempt'), [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('sales.login'));
    }
}
