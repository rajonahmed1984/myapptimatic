<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AccountingEntry;
use App\Models\CommissionEarning;
use App\Models\Customer;
use App\Models\ExpenseInvoice;
use App\Models\Invoice;
use App\Models\SalesRepresentative;
use App\Models\User;
use App\Services\BusinessStatusSummaryService;
use App\Services\ExpenseEntryService;
use App\Services\IncomeEntryService;
use App\Services\TaskQueryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessStatusSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function metrics_use_database_aggregates_and_short_lived_cache_can_be_bypassed(): void
    {
        Carbon::setTestNow('2026-07-18 12:00:00');
        config()->set('cache.default', 'array');
        config()->set('performance.business_status_cache_seconds', 60);
        Cache::flush();

        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create([
            'name' => 'Phase Five Customer',
            'email' => 'phase-five@example.test',
            'status' => 'active',
        ]);

        $exclusiveInvoice = $this->createInvoice($customer, [
            'number' => 'PHASE5-EXCLUSIVE',
            'status' => 'unpaid',
            'subtotal' => 100,
            'tax_amount' => 15,
            'tax_mode' => 'exclusive',
            'total' => 115,
            'due_date' => '2026-07-25',
        ]);
        $this->createInvoice($customer, [
            'number' => 'PHASE5-INCLUSIVE',
            'status' => 'overdue',
            'subtotal' => 200,
            'tax_amount' => 20,
            'tax_mode' => 'inclusive',
            'total' => 200,
            'due_date' => '2026-07-20',
        ]);

        AccountingEntry::create([
            'entry_date' => '2026-07-05',
            'type' => 'payment',
            'amount' => 40,
            'currency' => 'BDT',
            'reference' => 'PHASE5-PAYMENT',
            'customer_id' => $customer->id,
            'invoice_id' => $exclusiveInvoice->id,
            'created_by' => $admin->id,
        ]);

        ExpenseInvoice::create([
            'source_type' => 'phase_five',
            'source_id' => 1,
            'expense_type' => 'one_time',
            'invoice_no' => 'PHASE5-EXPENSE',
            'status' => 'unpaid',
            'invoice_date' => '2026-07-18',
            'due_date' => '2026-07-22',
            'amount' => 50,
            'currency' => 'BDT',
            'created_by' => $admin->id,
        ]);

        $salesRep = SalesRepresentative::create(['name' => 'Phase5 Rep', 'status' => 'active']);

        $this->createCommission($salesRep->id, 'payable', 30, 'phase5-payable');
        $this->createCommission($salesRep->id, 'paid', 20, 'phase5-paid');
        $this->createCommission($salesRep->id, 'reversed', 99, 'phase5-reversed');

        $incomeSummary = [
            'total' => 165.0,
            'manual' => 100.0,
            'system' => 40.0,
            'carrothost' => 25.0,
        ];
        $expenseSummary = [
            'total' => 60.0,
            'manual' => 30.0,
            'salary' => 20.0,
            'contract_payout' => 10.0,
            'sales_payout' => 0.0,
            'payout' => 30.0,
        ];
        $taskSummary = ['total' => 3, 'open' => 2];

        $incomeService = Mockery::mock(IncomeEntryService::class);
        $incomeService->shouldReceive('summary')->twice()->andReturn($incomeSummary);
        $expenseService = Mockery::mock(ExpenseEntryService::class);
        $expenseService->shouldReceive('summary')->twice()->andReturn($expenseSummary);
        $taskQueryService = Mockery::mock(TaskQueryService::class);
        $taskQueryService->shouldReceive('tasksSummaryForUser')->twice()->with($admin)->andReturn($taskSummary);

        $service = app(BusinessStatusSummaryService::class);
        $startDate = Carbon::parse('2026-07-01')->startOfDay();
        $endDate = Carbon::parse('2026-07-31')->endOfDay();

        $metrics = $service->buildMetricsCached(
            $startDate,
            $endDate,
            30,
            $admin,
            $incomeService,
            $expenseService,
            $taskQueryService
        );
        $cachedMetrics = $service->buildMetricsCached(
            $startDate,
            $endDate,
            30,
            $admin,
            $incomeService,
            $expenseService,
            $taskQueryService
        );
        $freshMetrics = $service->buildMetricsCached(
            $startDate,
            $endDate,
            30,
            $admin,
            $incomeService,
            $expenseService,
            $taskQueryService,
            true
        );

        $this->assertSame($metrics, $cachedMetrics);
        $this->assertSame($metrics, $freshMetrics);
        $this->assertSame(165.0, data_get($metrics, 'finance.income_total'));
        $this->assertSame(60.0, data_get($metrics, 'finance.expense_total'));
        $this->assertSame(105.0, data_get($metrics, 'finance.net_profit'));
        $this->assertSame(65.0, data_get($metrics, 'finance.received_income'));
        $this->assertSame(30.0, data_get($metrics, 'finance.payout_expense'));
        $this->assertSame(35.0, data_get($metrics, 'finance.net_cashflow'));

        $this->assertSame(300.0, data_get($metrics, 'tax.taxable_base'));
        $this->assertSame(35.0, data_get($metrics, 'tax.tax_amount'));
        $this->assertSame(315.0, data_get($metrics, 'tax.tax_gross'));
        $this->assertSame(15.0, data_get($metrics, 'tax.tax_exclusive'));
        $this->assertSame(20.0, data_get($metrics, 'tax.tax_inclusive'));

        $this->assertSame(50.0, data_get($metrics, 'commission.total_earned'));
        $this->assertSame(30.0, data_get($metrics, 'commission.payable'));
        $this->assertSame(20.0, data_get($metrics, 'commission.paid'));
        $this->assertSame(30.0, data_get($metrics, 'commission.outstanding'));

        $this->assertSame(315.0, data_get($metrics, 'projections.income_due_next_window'));
        $this->assertSame(2, data_get($metrics, 'projections.income_due_count'));
        $this->assertSame(50.0, data_get($metrics, 'projections.expense_due_next_window'));
        $this->assertSame(1, data_get($metrics, 'projections.expense_due_count'));
        $this->assertSame(200.0, data_get($metrics, 'projections.overdue_invoice_total'));
        $this->assertSame(1, data_get($metrics, 'projections.overdue_invoice_count'));

        Carbon::setTestNow();
    }

    private function createInvoice(Customer $customer, array $overrides): Invoice
    {
        return Invoice::create(array_merge([
            'customer_id' => $customer->id,
            'number' => 'PHASE5-INVOICE',
            'status' => 'unpaid',
            'issue_date' => '2026-07-10',
            'due_date' => '2026-07-25',
            'subtotal' => 100,
            'tax_rate_percent' => 15,
            'tax_mode' => 'exclusive',
            'tax_amount' => 15,
            'late_fee' => 0,
            'total' => 115,
            'currency' => 'BDT',
            'type' => 'manual',
        ], $overrides));
    }

    private function createCommission(int $salesRepId, string $status, float $amount, string $key): void
    {
        CommissionEarning::create([
            'sales_representative_id' => $salesRepId,
            'source_type' => 'project',
            'source_id' => 1,
            'currency' => 'BDT',
            'paid_amount' => 100,
            'commission_amount' => $amount,
            'status' => $status,
            'idempotency_key' => $key,
        ]);
    }
}
