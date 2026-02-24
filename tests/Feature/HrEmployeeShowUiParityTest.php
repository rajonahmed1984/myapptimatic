<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HrEmployeeShowUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function employee_show_renders_inertia_page_for_admin(): void
    {
        $admin = $this->makeAdmin();
        $employee = $this->makeEmployee('show-employee@example.test', 'monthly');

        $this->actingAs($admin)
            ->get(route('admin.hr.employees.show', $employee))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/', false);
    }

    #[Test]
    public function employee_show_invalid_tab_falls_back_to_profile_contract(): void
    {
        $admin = $this->makeAdmin();
        $employee = $this->makeEmployee('tab-fallback@example.test', 'monthly');

        $this->actingAs($admin)
            ->get(route('admin.hr.employees.show', ['employee' => $employee->id, 'tab' => 'unknown']))
            ->assertOk()
            ->assertSee('Profile')
            ->assertSee('tab=profile');
    }

    #[Test]
    public function employee_show_allows_project_base_earnings_tab_contract(): void
    {
        $admin = $this->makeAdmin();
        $employee = $this->makeEmployee('earnings-tab@example.test', 'project_base');

        $this->actingAs($admin)
            ->get(route('admin.hr.employees.show', ['employee' => $employee->id, 'tab' => 'earnings']))
            ->assertOk();
    }

    #[Test]
    public function employee_advance_payout_contract_is_preserved(): void
    {
        $admin = $this->makeAdmin();
        $employee = $this->makeEmployee('advance-employee@example.test', 'monthly');
        $showUrl = route('admin.hr.employees.show', ['employee' => $employee->id, 'tab' => 'payroll']);

        $this->actingAs($admin)->from($showUrl)
            ->post(route('admin.hr.employees.advance-payout', $employee), [
                'amount' => 1000,
                'currency' => 'BDT',
                'paid_at' => now()->toDateString(),
            ])
            ->assertRedirect($showUrl)
            ->assertSessionHas('status', 'Advance payout recorded.');
    }

    #[Test]
    public function employee_advance_payout_validation_contract_is_preserved(): void
    {
        $admin = $this->makeAdmin();
        $employee = $this->makeEmployee('advance-validation@example.test', 'monthly');
        $showUrl = route('admin.hr.employees.show', ['employee' => $employee->id, 'tab' => 'payroll']);

        $this->actingAs($admin)->from($showUrl)
            ->post(route('admin.hr.employees.advance-payout', $employee), [
                'currency' => 'BDT',
                'paid_at' => now()->toDateString(),
            ])
            ->assertRedirect($showUrl)
            ->assertSessionHasErrors(['amount']);
    }

    #[Test]
    public function employee_show_remains_forbidden_for_client_role(): void
    {
        $client = User::factory()->create(['role' => Role::CLIENT]);
        $employee = $this->makeEmployee('blocked-employee@example.test', 'monthly', false);

        $this->actingAs($client)
            ->get(route('admin.hr.employees.show', $employee))
            ->assertForbidden();
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => Role::MASTER_ADMIN]);
    }

    private function makeEmployee(string $email, string $salaryType, bool $withCompensation = true): Employee
    {
        $employee = Employee::create([
            'name' => 'Employee '.strtok($email, '@'),
            'email' => $email,
            'employment_type' => 'full_time',
            'work_mode' => 'on_site',
            'join_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        if ($withCompensation) {
            EmployeeCompensation::create([
                'employee_id' => $employee->id,
                'salary_type' => $salaryType,
                'currency' => 'BDT',
                'basic_pay' => 50000,
                'effective_from' => now()->toDateString(),
                'is_active' => true,
            ]);
        }

        return $employee;
    }
}
