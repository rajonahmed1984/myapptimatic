<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HrEmployeesFormUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function employee_create_and_edit_render_direct_inertia_components_for_admin(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $employeeUser = User::factory()->create();
        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'name' => 'HR Form Employee',
            'email' => 'hr-form-employee@example.test',
            'employment_type' => 'full_time',
            'work_mode' => 'on_site',
            'join_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.hr.employees.create'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Hr\\/Employees\\/Create', false);

        $this->actingAs($admin)
            ->get(route('admin.hr.employees.edit', $employee))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Hr\\/Employees\\/Edit', false);
    }

    #[Test]
    public function employee_update_contract_is_preserved(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $employee = Employee::create([
            'name' => 'Employee Contract',
            'email' => 'employee-contract@example.test',
            'employment_type' => 'full_time',
            'work_mode' => 'remote',
            'join_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.hr.employees.update', $employee), [
                'name' => 'Employee Contract Updated',
                'email' => 'employee-contract@example.test',
                'employment_type' => 'full_time',
                'work_mode' => 'remote',
                'join_date' => now()->toDateString(),
                'status' => 'active',
                'salary_type' => 'monthly',
                'currency' => 'BDT',
                'basic_pay' => 1000,
            ])
            ->assertRedirect(route('admin.hr.employees.index'))
            ->assertSessionHas('status', 'Employee updated.');
    }

    #[Test]
    public function employee_create_and_edit_remain_forbidden_for_client_role(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        $employee = Employee::create([
            'name' => 'Client Blocked Employee',
            'email' => 'client-blocked-employee@example.test',
            'employment_type' => 'full_time',
            'work_mode' => 'remote',
            'join_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $this->actingAs($client)
            ->get(route('admin.hr.employees.create'))
            ->assertForbidden();

        $this->actingAs($client)
            ->get(route('admin.hr.employees.edit', $employee))
            ->assertForbidden();
    }
}
