<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\EmployeePayout;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
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

        $response = $this->actingAs($admin)
            ->get(route('admin.hr.employees.show', $employee))
            ->assertOk();

        $props = $this->inertiaProps($response->getContent());
        $this->assertSame('Admin/Hr/Employees/Show', data_get($props, 'component'));
        $this->assertSame($employee->id, data_get($props, 'props.employee.id'));
    }

    #[Test]
    public function employee_show_invalid_tab_falls_back_to_profile_contract(): void
    {
        $admin = $this->makeAdmin();
        $employee = $this->makeEmployee('tab-fallback@example.test', 'monthly');

        $response = $this->actingAs($admin)
            ->get(route('admin.hr.employees.show', ['employee' => $employee->id, 'tab' => 'unknown']))
            ->assertOk();

        $props = $this->inertiaProps($response->getContent());
        $this->assertSame('profile', data_get($props, 'props.tab'));
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
    public function employee_show_allows_emails_tab_contract(): void
    {
        $admin = $this->makeAdmin();
        $employee = $this->makeEmployee('emails-tab@example.test', 'monthly');

        $response = $this->actingAs($admin)
            ->get(route('admin.hr.employees.show', ['employee' => $employee->id, 'tab' => 'emails']))
            ->assertOk();

        $props = $this->inertiaProps($response->getContent());
        $this->assertSame('emails', data_get($props, 'props.tab'));
        $this->assertIsArray(data_get($props, 'props.emailLogs'));
    }

    #[Test]
    public function employee_show_allows_log_tab_contract(): void
    {
        $admin = $this->makeAdmin();
        $employee = $this->makeEmployee('log-tab@example.test', 'monthly');

        $response = $this->actingAs($admin)
            ->get(route('admin.hr.employees.show', ['employee' => $employee->id, 'tab' => 'log']))
            ->assertOk();

        $props = $this->inertiaProps($response->getContent());
        $this->assertSame('log', data_get($props, 'props.tab'));
        $this->assertIsArray(data_get($props, 'props.activityLogs'));
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
                'coordination_month' => now()->format('Y-m'),
                'paid_at' => now()->toDateString(),
            ])
            ->assertRedirect($showUrl)
            ->assertSessionHas('status', 'Advance payout recorded.');

        $this->assertDatabaseHas('employee_payouts', [
            'employee_id' => $employee->id,
            'metadata->coordination_month' => now()->format('Y-m'),
        ]);
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
    public function employee_payroll_tab_exposes_payable_and_paid_including_advance_stats(): void
    {
        $admin = $this->makeAdmin();
        $employee = $this->makeEmployee('payroll-stats@example.test', 'monthly');

        $period = PayrollPeriod::create([
            'period_key' => '2026-03',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'status' => 'finalized',
        ]);

        PayrollItem::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'status' => 'partial',
            'pay_type' => 'monthly',
            'currency' => 'BDT',
            'net_pay' => 20000,
            'paid_amount' => 5000,
        ]);

        PayrollItem::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'status' => 'paid',
            'pay_type' => 'monthly',
            'currency' => 'BDT',
            'net_pay' => 10000,
            'paid_amount' => 10000,
        ]);

        EmployeePayout::create([
            'employee_id' => $employee->id,
            'amount' => 3000,
            'currency' => 'BDT',
            'metadata' => [
                'type' => 'advance',
                'advance_scope' => 'payroll',
            ],
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.hr.employees.show', ['employee' => $employee->id, 'tab' => 'payroll']))
            ->assertOk();

        $props = $this->inertiaProps($response->getContent());
        $this->assertEquals(15000.0, (float) data_get($props, 'props.workSessionStats.payable_amount'));
        $this->assertEquals(18000.0, (float) data_get($props, 'props.workSessionStats.paid_incl_advance'));
        $this->assertEquals(15000.0, (float) data_get($props, 'props.workSessionStats.payroll_paid_amount'));
        $this->assertEquals(3000.0, (float) data_get($props, 'props.workSessionStats.advance_paid_amount'));
    }

    #[Test]
    public function employee_payroll_tab_exposes_payroll_month_options_for_advance_coordination(): void
    {
        $admin = $this->makeAdmin();
        $employee = $this->makeEmployee('payroll-month-options@example.test', 'monthly');

        $response = $this->actingAs($admin)
            ->get(route('admin.hr.employees.show', ['employee' => $employee->id, 'tab' => 'payroll']))
            ->assertOk();

        $props = $this->inertiaProps($response->getContent());
        $options = (array) data_get($props, 'props.payrollMonthOptions');

        $this->assertNotEmpty($options);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}$/', (string) data_get($options, '0.value'));
    }

    #[Test]
    public function employee_payroll_items_are_sorted_by_latest_period_month_first(): void
    {
        $admin = $this->makeAdmin();
        $employee = $this->makeEmployee('payroll-order@example.test', 'monthly');

        $periodJan = PayrollPeriod::create([
            'period_key' => '2026-01',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'finalized',
        ]);
        $periodMar = PayrollPeriod::create([
            'period_key' => '2026-03',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'status' => 'finalized',
        ]);
        $periodFeb = PayrollPeriod::create([
            'period_key' => '2026-02',
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-28',
            'status' => 'finalized',
        ]);

        // Intentionally created in non-chronological order to verify sort behavior.
        PayrollItem::create([
            'payroll_period_id' => $periodJan->id,
            'employee_id' => $employee->id,
            'status' => 'approved',
            'pay_type' => 'monthly',
            'currency' => 'BDT',
            'net_pay' => 10000,
        ]);
        PayrollItem::create([
            'payroll_period_id' => $periodMar->id,
            'employee_id' => $employee->id,
            'status' => 'approved',
            'pay_type' => 'monthly',
            'currency' => 'BDT',
            'net_pay' => 12000,
        ]);
        PayrollItem::create([
            'payroll_period_id' => $periodFeb->id,
            'employee_id' => $employee->id,
            'status' => 'approved',
            'pay_type' => 'monthly',
            'currency' => 'BDT',
            'net_pay' => 11000,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.hr.employees.show', ['employee' => $employee->id, 'tab' => 'payroll']))
            ->assertOk();

        $props = $this->inertiaProps($response->getContent());
        $periodKeys = collect((array) data_get($props, 'props.recentPayrollItems'))
            ->map(fn (array $item) => (string) data_get($item, 'period.period_key'))
            ->filter()
            ->values()
            ->all();

        $this->assertSame(['2026-03', '2026-02', '2026-01'], array_slice($periodKeys, 0, 3));
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

    /**
     * @return array<string, mixed>
     */
    private function inertiaProps(string $html): array
    {
        preg_match('/data-page="([^"]+)"/', $html, $matches);
        $this->assertArrayHasKey(1, $matches, 'Inertia payload is missing in response.');

        $decoded = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        $payload = json_decode($decoded, true);
        $this->assertIsArray($payload);

        return $payload;
    }
}
