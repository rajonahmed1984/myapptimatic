<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HrEmployeePayoutsUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function employee_payout_create_renders_direct_inertia_component_for_admin(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $employee = Employee::create([
            'name' => 'Payout Employee',
            'email' => 'payout-employee@example.test',
            'employment_type' => 'contract',
            'work_mode' => 'remote',
            'join_date' => now()->toDateString(),
            'status' => 'active',
        ]);
        EmployeeCompensation::create([
            'employee_id' => $employee->id,
            'salary_type' => 'project_base',
            'currency' => 'BDT',
            'basic_pay' => 0,
            'effective_from' => now()->toDateString(),
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.hr.employee-payouts.create'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Hr\\/Employees\\/Payouts\\/Create', false);
    }

    #[Test]
    public function employee_payout_store_contract_is_preserved(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $employee = Employee::create([
            'name' => 'Payout Store Employee',
            'email' => 'payout-store-employee@example.test',
            'employment_type' => 'contract',
            'work_mode' => 'remote',
            'join_date' => now()->toDateString(),
            'status' => 'active',
        ]);
        EmployeeCompensation::create([
            'employee_id' => $employee->id,
            'salary_type' => 'project_base',
            'currency' => 'BDT',
            'basic_pay' => 0,
            'effective_from' => now()->toDateString(),
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'Payout Customer',
            'status' => 'active',
            'email' => 'payout-customer@example.test',
        ]);

        $project = Project::create([
            'customer_id' => $customer->id,
            'name' => 'Payout Project',
            'status' => 'active',
            'currency' => 'BDT',
            'contract_employee_total_earned' => 1000,
            'contract_employee_payable' => 400,
            'contract_employee_payout_status' => 'earned',
        ]);
        $project->employees()->attach($employee->id);

        $this->actingAs($admin)
            ->post(route('admin.hr.employee-payouts.store'), [
                'employee_id' => $employee->id,
                'project_ids' => [$project->id],
            ])
            ->assertRedirect(route('admin.hr.employees.show', ['employee' => $employee->id, 'tab' => 'payouts']))
            ->assertSessionHas('status', 'Employee payout recorded.');
    }

    #[Test]
    public function employee_payout_create_remains_forbidden_for_client_role(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        $this->actingAs($client)
            ->get(route('admin.hr.employee-payouts.create'))
            ->assertForbidden();
    }
}
