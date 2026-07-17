<?php

namespace Tests\Feature;

use App\Models\AccountingEntry;
use App\Models\CommissionPayout;
use App\Models\Employee;
use App\Models\EmployeePayout;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\SalesRepresentative;
use App\Services\ExpenseEntryService;
use App\Services\IncomeEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EntrySummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function aggregate_summaries_match_the_existing_entry_totals_for_the_same_period(): void
    {
        $incomeCategory = IncomeCategory::create([
            'name' => 'Phase Nine Income',
            'status' => 'active',
        ]);
        Income::create([
            'income_category_id' => $incomeCategory->id,
            'title' => 'Included income',
            'amount' => 100,
            'income_date' => '2026-07-10',
        ]);
        Income::create([
            'income_category_id' => $incomeCategory->id,
            'title' => 'Excluded income',
            'amount' => 999,
            'income_date' => '2026-06-30',
        ]);
        AccountingEntry::create([
            'entry_date' => '2026-07-11',
            'type' => 'payment',
            'amount' => 40,
            'currency' => 'BDT',
        ]);
        AccountingEntry::create([
            'entry_date' => '2026-07-12',
            'type' => 'expense',
            'amount' => 500,
            'currency' => 'BDT',
        ]);

        $expenseCategory = ExpenseCategory::create([
            'name' => 'Phase Nine Expense',
            'status' => 'active',
        ]);
        Expense::create([
            'category_id' => $expenseCategory->id,
            'title' => 'Included expense',
            'amount' => 30,
            'expense_date' => '2026-07-13',
            'type' => 'one_time',
        ]);
        Expense::create([
            'category_id' => $expenseCategory->id,
            'title' => 'Excluded expense',
            'amount' => 777,
            'expense_date' => '2026-08-01',
            'type' => 'one_time',
        ]);

        $employee = Employee::create([
            'name' => 'Phase Nine Employee',
            'email' => 'phase-nine-employee@example.test',
            'status' => 'active',
        ]);
        $period = PayrollPeriod::create([
            'period_key' => '2026-07',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'paid',
        ]);
        PayrollItem::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'status' => 'paid',
            'net_pay' => 20,
            'paid_at' => '2026-07-14 10:00:00',
        ]);
        PayrollItem::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'status' => 'approved',
            'net_pay' => 800,
            'paid_at' => '2026-07-14 10:00:00',
        ]);
        EmployeePayout::create([
            'employee_id' => $employee->id,
            'amount' => 10,
            'paid_at' => '2026-07-15 10:00:00',
        ]);

        $salesRep = SalesRepresentative::create([
            'name' => 'Phase Nine Sales Rep',
            'email' => 'phase-nine-sales@example.test',
            'status' => 'active',
        ]);
        CommissionPayout::create([
            'sales_representative_id' => $salesRep->id,
            'total_amount' => 5,
            'currency' => 'BDT',
            'status' => 'paid',
            'paid_at' => '2026-07-16 10:00:00',
        ]);
        CommissionPayout::create([
            'sales_representative_id' => $salesRep->id,
            'total_amount' => 600,
            'currency' => 'BDT',
            'status' => 'draft',
            'paid_at' => '2026-07-16 10:00:00',
        ]);

        $filters = [
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
        ];

        $incomeService = app(IncomeEntryService::class);
        $incomeFilters = array_merge($filters, ['sources' => ['manual', 'system']]);
        $incomeEntries = $incomeService->entries($incomeFilters);
        $incomeSummary = $incomeService->summary($incomeFilters);

        $this->assertSame(140.0, $incomeSummary['total']);
        $this->assertSame(100.0, $incomeSummary['manual']);
        $this->assertSame(40.0, $incomeSummary['system']);
        $this->assertSame((float) $incomeEntries->sum('amount'), $incomeSummary['total']);

        $expenseService = app(ExpenseEntryService::class);
        $expenseEntries = $expenseService->entries($filters);
        $expenseSummary = $expenseService->summary($filters);

        $this->assertSame(65.0, $expenseSummary['total']);
        $this->assertSame(30.0, $expenseSummary['manual']);
        $this->assertSame(35.0, $expenseSummary['payout']);
        $this->assertSame((float) $expenseEntries->sum('amount'), $expenseSummary['total']);
    }
}
