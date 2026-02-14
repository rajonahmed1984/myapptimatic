<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\SalesRepresentative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginSessionPersistenceTest extends TestCase
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

    public function test_wrong_credentials_redirect_back_with_errors_for_all_login_forms(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'password' => Hash::make('password123'),
        ]);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
            'password' => Hash::make('password123'),
        ]);

        $employeeUser = User::factory()->create([
            'role' => Role::EMPLOYEE,
            'password' => Hash::make('password123'),
        ]);

        Employee::create([
            'user_id' => $employeeUser->id,
            'name' => $employeeUser->name,
            'email' => $employeeUser->email,
            'status' => 'active',
        ]);

        $salesUser = User::factory()->create([
            'role' => Role::SALES,
            'password' => Hash::make('password123'),
        ]);

        SalesRepresentative::create([
            'user_id' => $salesUser->id,
            'name' => $salesUser->name,
            'email' => $salesUser->email,
            'status' => 'active',
        ]);

        $supportUser = User::factory()->create([
            'role' => Role::SUPPORT,
            'password' => Hash::make('password123'),
        ]);

        $cases = [
            [$client, 'login', 'login.attempt'],
            [$admin, 'admin.login', 'admin.login.attempt'],
            [$employeeUser, 'employee.login', 'employee.login.attempt'],
            [$salesUser, 'sales.login', 'sales.login.attempt'],
            [$supportUser, 'support.login', 'support.login.attempt'],
        ];

        foreach ($cases as [$user, $loginRoute, $attemptRoute]) {
            $response = $this->from(route($loginRoute))->post(route($attemptRoute), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);

            $response->assertRedirect(route($loginRoute));
            $response->assertSessionHasErrors('email');
        }
    }

    public function test_successful_login_persists_session_for_web_guard(): void
    {
        $user = User::factory()->create([
            'role' => Role::CLIENT,
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post(route('login.attempt'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('client.dashboard'));
        $response->assertCookie(config('session.cookie'));
        $this->assertAuthenticatedAs($user, 'web');
        $this->get(route('login'))->assertRedirect(route('client.dashboard'));
    }

    public function test_successful_login_persists_session_for_admin_login(): void
    {
        $user = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post(route('admin.login.attempt'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertCookie(config('session.cookie'));
        $this->assertAuthenticatedAs($user, 'web');
        $this->get(route('admin.login'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_successful_login_persists_session_for_employee_guard(): void
    {
        $user = User::factory()->create([
            'role' => Role::EMPLOYEE,
            'password' => Hash::make('password123'),
        ]);

        Employee::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => 'active',
        ]);

        $response = $this->post(route('employee.login.attempt'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('employee.dashboard'));
        $response->assertCookie(config('session.cookie'));
        $this->assertAuthenticatedAs($user, 'employee');
        $this->get(route('employee.login'))->assertRedirect(route('employee.dashboard'));
    }

    public function test_successful_login_persists_session_for_sales_guard(): void
    {
        $user = User::factory()->create([
            'role' => Role::SALES,
            'password' => Hash::make('password123'),
        ]);

        SalesRepresentative::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => 'active',
        ]);

        $response = $this->post(route('sales.login.attempt'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('rep.dashboard'));
        $response->assertCookie(config('session.cookie'));
        $this->assertAuthenticatedAs($user, 'sales');
        $this->get(route('sales.login'))->assertRedirect(route('rep.dashboard'));
    }

    public function test_successful_login_persists_session_for_support_guard(): void
    {
        $user = User::factory()->create([
            'role' => Role::SUPPORT,
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post(route('support.login.attempt'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('support.dashboard'));
        $response->assertCookie(config('session.cookie'));
        $this->assertAuthenticatedAs($user, 'support');
        $this->get(route('support.login'))->assertRedirect(route('support.dashboard'));
    }
}
