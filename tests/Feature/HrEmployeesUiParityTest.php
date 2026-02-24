<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HrEmployeesUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function employees_index_renders_direct_inertia_component_for_admin(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $employeeUser = User::factory()->create();
        Employee::create([
            'user_id' => $employeeUser->id,
            'name' => 'HR Employee',
            'email' => 'hr-employee@example.test',
            'employment_type' => 'full_time',
            'work_mode' => 'on_site',
            'join_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.hr.employees.index'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Hr\\/Employees\\/Index', false);
    }

    #[Test]
    public function employee_destroy_contract_is_preserved(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $employeeUser = User::factory()->create();
        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'name' => 'Employee Remove',
            'email' => 'employee-remove@example.test',
            'employment_type' => 'full_time',
            'work_mode' => 'on_site',
            'join_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.hr.employees.destroy', $employee))
            ->assertRedirect(route('admin.hr.employees.index'))
            ->assertSessionHas('status', 'Employee removed.');
    }

    #[Test]
    public function employees_index_remains_forbidden_for_client_role(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        $this->actingAs($client)
            ->get(route('admin.hr.employees.index'))
            ->assertForbidden();
    }
}
