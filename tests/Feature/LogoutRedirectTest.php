<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Http\Middleware\TrackAuthenticatedUserActivity;
use App\Http\Middleware\TrackEmployeeActivity;
use App\Models\Employee;
use App\Models\SalesRepresentative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_logout_redirects_to_admin_login(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $this->get(route('admin.login'));

        $response = $this->actingAs($admin, 'web')
            ->post(route('logout'));

        $response->assertRedirect(route('admin.login'));
        $this->assertGuest('web');
    }

    public function test_client_logout_redirects_to_login(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        $this->get(route('login'));

        $response = $this->actingAs($client, 'web')
            ->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_employee_logout_redirects_to_employee_login(): void
    {
        $user = User::factory()->create([
            'role' => Role::EMPLOYEE,
        ]);

        Employee::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => 'active',
        ]);

        $this->get(route('employee.login'));

        $response = $this->withoutMiddleware([
            TrackEmployeeActivity::class,
            TrackAuthenticatedUserActivity::class,
        ])->actingAs($user, 'employee')
            ->post(route('logout'));

        $response->assertRedirect(route('employee.login'));
        $this->assertGuest('employee');
    }

    public function test_sales_logout_redirects_to_sales_login(): void
    {
        $user = User::factory()->create([
            'role' => Role::SALES,
        ]);

        SalesRepresentative::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => 'active',
        ]);

        $this->get(route('sales.login'));

        $response = $this->withoutMiddleware([
            TrackAuthenticatedUserActivity::class,
        ])->actingAs($user, 'sales')
            ->post(route('logout'));

        $response->assertRedirect(route('sales.login'));
        $this->assertGuest('sales');
    }

    public function test_support_logout_redirects_to_support_login(): void
    {
        $user = User::factory()->create([
            'role' => Role::SUPPORT,
        ]);

        $this->get(route('support.login'));

        $response = $this->withoutMiddleware([
            TrackAuthenticatedUserActivity::class,
        ])->actingAs($user, 'support')
            ->post(route('logout'));

        $response->assertRedirect(route('support.login'));
        $this->assertGuest('support');
    }
}
