<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\SalesRepresentative;
use App\Models\User;
use App\Services\ProjectSalaryCostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminProjectProfitTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function profit_and_remaining_budget_deduct_sales_rep_and_contract_payouts(): void
    {
        $customer = Customer::create([
            'name' => 'Profit Customer',
        ]);

        $project = Project::create([
            'name' => 'Profit Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 20000,
            'initial_payment_amount' => 0,
            'currency' => 'BDT',
            'hourly_cost' => 0,
            'actual_hours' => 0,
            'contract_amount' => 0,
        ]);

        $salesUser = User::factory()->create();
        $salesRep = SalesRepresentative::create([
            'user_id' => $salesUser->id,
            'name' => 'Sales Rep',
            'email' => 'rep@example.com',
            'status' => 'active',
        ]);

        $project->salesRepresentatives()->attach($salesRep->id, [
            'amount' => 7000,
        ]);

        $admin = User::factory()->create([
            'role' => 'master_admin',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.projects.show', $project));

        $response->assertOk();
        $response->assertSee('Remaining budget: BDT 20,000.00');
        $response->assertSee('Profit: BDT 13,000.00');
    }

    #[Test]
    public function employee_cost_prorates_monthly_salary_and_splits_it_across_overlapping_projects(): void
    {
        Carbon::setTestNow('2026-01-31 12:00:00');

        $employee = $this->monthlyEmployee('split@example.test', 31000);
        $project = $this->salaryProject('Main Project', '2026-01-01', 100000);
        $otherProject = $this->salaryProject('Overlap Project', '2026-01-17', 50000);
        $project->employees()->attach($employee->id);
        $otherProject->employees()->attach($employee->id);

        $admin = User::factory()->create(['role' => 'master_admin']);

        // 16 solo days at 1,000 plus 15 shared days at 500.
        $this->actingAs($admin)
            ->get(route('admin.projects.show', $project))
            ->assertInertia(fn ($page) => $page
                ->where('project.financials.employee_salary_total_display', 'BDT 23,500.00')
                ->where('project.financials.profit_display', 'BDT 76,500.00')
                ->where('project.dates.duration_days', 31)
                ->where('project.team.employee_costs.0.days', 31)
                ->where('project.team.employee_costs.0.shared_days', 15)
                ->where('project.team.employee_costs.0.salary_display', 'BDT 23,500.00'));

        Carbon::setTestNow();
    }

    #[Test]
    public function completed_project_salary_cost_stops_at_its_end_date(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');
        $employee = $this->monthlyEmployee('closed@example.test', 31000);
        $project = $this->salaryProject('Closed Project', '2026-01-01', 100000);
        $project->employees()->attach($employee->id);

        Carbon::setTestNow('2026-01-10 12:00:00');
        $project->update(['status' => 'complete', 'expected_end_date' => '2026-01-20']);

        Carbon::setTestNow('2026-03-01 12:00:00');
        $this->assertSame(10000.0, app(ProjectSalaryCostService::class)->forProject($project)['total']);

        Carbon::setTestNow();
    }

    #[Test]
    public function contract_and_non_monthly_employees_do_not_add_salary_cost(): void
    {
        Carbon::setTestNow('2026-01-31 12:00:00');

        $contractEmployee = $this->monthlyEmployee('contract@example.test', 31000, 'contract');
        $projectBaseEmployee = $this->monthlyEmployee('base@example.test', 31000, 'full_time', 'project_base');
        $project = $this->salaryProject('Mixed Project', '2026-01-01', 100000);
        $project->update(['contract_amount' => 5000]);
        $project->employees()->attach([$contractEmployee->id, $projectBaseEmployee->id]);

        $admin = User::factory()->create(['role' => 'master_admin']);

        $this->actingAs($admin)
            ->get(route('admin.projects.show', $project))
            ->assertInertia(fn ($page) => $page
                ->where('project.financials.employee_salary_total_display', 'BDT 5,000.00')
                ->where('project.financials.employee_cost_not_counted', [$projectBaseEmployee->name]));

        Carbon::setTestNow();
    }

    private function monthlyEmployee(string $email, float $pay, string $employmentType = 'full_time', string $salaryType = 'monthly'): Employee
    {
        $employee = Employee::create([
            'name' => $email,
            'email' => $email,
            'employment_type' => $employmentType,
            'join_date' => '2025-01-01',
            'status' => 'active',
        ]);

        EmployeeCompensation::create([
            'employee_id' => $employee->id,
            'salary_type' => $salaryType,
            'currency' => 'BDT',
            'basic_pay' => $pay,
            'effective_from' => '2025-01-01',
            'is_active' => true,
        ]);

        return $employee;
    }

    private function salaryProject(string $name, string $startDate, float $budget): Project
    {
        $customer = Customer::create(['name' => $name.' Customer']);

        return Project::create([
            'name' => $name,
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'start_date' => $startDate,
            'total_budget' => $budget,
            'currency' => 'BDT',
        ]);
    }

    #[Test]
    public function invoiceable_remaining_excludes_unpaid_invoices_and_ignores_vat_on_paid_ones(): void
    {
        $customer = Customer::create(['name' => 'Remaining Customer']);

        $project = Project::create([
            'name' => 'Remaining Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 200000,
            'initial_payment_amount' => 50000,
            'currency' => 'BDT',
            'hourly_cost' => 0,
            'actual_hours' => 0,
            'contract_amount' => 0,
        ]);

        // Unpaid initial payment invoice: still counts as remaining, but must
        // not be offered for invoicing a second time.
        Invoice::create([
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'number' => 'INV-R-1',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'subtotal' => 50000,
            'tax_amount' => 0,
            'late_fee' => 0,
            'total' => 50000,
            'currency' => 'BDT',
            'type' => 'project_initial_payment',
        ]);

        // Paid invoice with VAT: only the pre-VAT subtotal reduces the budget.
        Invoice::create([
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'number' => 'INV-R-2',
            'status' => 'paid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'subtotal' => 40000,
            'tax_amount' => 6000,
            'late_fee' => 0,
            'total' => 46000,
            'currency' => 'BDT',
            'type' => 'project_remaining_budget',
        ]);

        $admin = User::factory()->create(['role' => 'master_admin']);

        $response = $this->actingAs($admin)->get(route('admin.projects.show', $project));

        $response->assertOk();
        $response->assertSee('Remaining budget: BDT 160,000.00');
        $response->assertInertia(fn ($page) => $page->where('project.financials.remaining_budget_invoiceable_display', 'BDT 110,000.00'));

        $this->actingAs($admin)
            ->post(route('admin.projects.invoice-remaining', $project), ['amount' => 150000])
            ->assertSessionHasErrors('amount');
    }
}
