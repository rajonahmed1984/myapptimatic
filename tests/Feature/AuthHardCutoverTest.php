<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\SalesRepresentative;
use App\Models\User;
use App\Support\Auth\RateLimiters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthHardCutoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('recaptcha.enabled', false);
    }

    public function test_each_portal_wrong_credentials_redirects_back_with_errors(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'password' => 'secret-pass',
        ]);
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
            'password' => 'secret-pass',
        ]);
        $employee = $this->createEmployeeUser('secret-pass');
        $sales = $this->createSalesUser('secret-pass');
        $support = User::factory()->create([
            'role' => Role::SUPPORT,
            'password' => 'secret-pass',
        ]);

        $cases = [
            [$client, 'login', 'login.attempt'],
            [$admin, 'admin.login', 'admin.login.attempt'],
            [$employee, 'employee.login', 'employee.login.attempt'],
            [$sales, 'sales.login', 'sales.login.attempt'],
            [$support, 'support.login', 'support.login.attempt'],
        ];

        foreach ($cases as [$user, $loginRoute, $attemptRoute]) {
            $response = $this->from(route($loginRoute))->post(route($attemptRoute), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);

            $response->assertRedirect(route($loginRoute));
            $response->assertSessionHasErrors(['email']);
        }
    }

    public function test_each_portal_correct_credentials_authenticates_and_redirects_to_portal_default(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'password' => 'secret-pass',
        ]);
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
            'password' => 'secret-pass',
        ]);
        $employee = $this->createEmployeeUser('secret-pass');
        $sales = $this->createSalesUser('secret-pass');
        $support = User::factory()->create([
            'role' => Role::SUPPORT,
            'password' => 'secret-pass',
        ]);

        $cases = [
            [$client, 'login.attempt', 'client.dashboard', 'web', 'logout'],
            [$admin, 'admin.login.attempt', 'admin.dashboard', 'web', 'admin.logout'],
            [$employee, 'employee.login.attempt', 'employee.dashboard', 'employee', 'employee.logout'],
            [$sales, 'sales.login.attempt', 'rep.dashboard', 'sales', 'rep.logout'],
            [$support, 'support.login.attempt', 'support.dashboard', 'support', 'support.logout'],
        ];

        foreach ($cases as [$user, $attemptRoute, $targetRoute, $guard, $logoutRoute]) {
            $response = $this->post(route($attemptRoute), [
                'email' => $user->email,
                'password' => 'secret-pass',
            ]);

            $response->assertRedirect(route($targetRoute, [], false));
            $this->assertAuthenticatedAs($user, $guard);
            $this->post(route($logoutRoute));
        }
    }

    public function test_logout_redirects_to_same_portal_login_based_on_session_portal(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);
        $employee = $this->createEmployeeUser();
        $sales = $this->createSalesUser();
        $support = User::factory()->create([
            'role' => Role::SUPPORT,
        ]);

        $cases = [
            ['login', $client, 'web', 'logout', 'login'],
            ['admin.login', $admin, 'web', 'admin.logout', 'admin.login'],
            ['employee.login', $employee, 'employee', 'employee.logout', 'employee.login'],
            ['sales.login', $sales, 'sales', 'rep.logout', 'sales.login'],
            ['support.login', $support, 'support', 'support.logout', 'support.login'],
        ];

        foreach ($cases as [$loginRoute, $user, $guard, $logoutRoute, $expectedLoginRoute]) {
            $this->get(route($loginRoute));

            $response = $this->actingAs($user, $guard)
                ->post(route($logoutRoute));

            $response->assertRedirect(route($expectedLoginRoute));
            $this->assertGuest($guard);
        }
    }

    public function test_portal_login_rate_limiting_blocks_after_max_attempts(): void
    {
        $user = $this->createEmployeeUser('secret-pass');

        for ($attempt = 1; $attempt <= RateLimiters::LOGIN_MAX_ATTEMPTS; $attempt++) {
            $response = $this->from(route('employee.login'))->post(route('employee.login.attempt'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);

            $response->assertRedirect(route('employee.login'));
        }

        $blocked = $this->from(route('employee.login'))->post(route('employee.login.attempt'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $blocked->assertRedirect(route('employee.login'));
        $blocked->assertSessionHasErrors(['email']);
        $this->assertGuest('employee');
    }

    public function test_authenticated_but_unauthorized_user_gets_403_on_admin_routes(): void
    {
        $user = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        $response = $this->actingAs($user, 'web')
            ->get(route('admin.dashboard'));

        $response->assertForbidden();
    }

    private function createEmployeeUser(string $password = 'password'): User
    {
        $user = User::factory()->create([
            'role' => Role::EMPLOYEE,
            'password' => $password,
        ]);

        Employee::query()->create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => 'active',
        ]);

        return $user;
    }

    private function createSalesUser(string $password = 'password'): User
    {
        $user = User::factory()->create([
            'role' => Role::SALES,
            'password' => $password,
        ]);

        SalesRepresentative::query()->create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => 'active',
        ]);

        return $user;
    }
}
