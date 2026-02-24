<?php

namespace Tests\Feature;

use App\Models\AccountingEntry;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\Invoice;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\TaxRate;
use App\Models\TaxSetting;
use App\Models\User;
use App\Services\IncomeEntryService;
use App\Services\InvoiceTaxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinanceIncomeTaxTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function master_admin_can_manage_income_categories(): void
    {
        $admin = User::factory()->create([
            'role' => 'master_admin',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.income.categories.store'), [
            'name' => 'Consulting',
            'description' => 'Consulting fees',
            'status' => 'active',
        ]);

        $response->assertSessionHasNoErrors();

        $category = IncomeCategory::query()->first();
        $this->assertNotNull($category);

        $updateResponse = $this->actingAs($admin)->put(route('admin.income.categories.update', $category), [
            'name' => 'Consulting Updated',
            'description' => 'Updated',
            'status' => 'inactive',
        ]);

        $updateResponse->assertRedirect(route('admin.income.categories.index'));

        $deleteResponse = $this->actingAs($admin)->delete(route('admin.income.categories.destroy', $category));

        $deleteResponse->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('income_categories', ['id' => $category->id]);
    }

    #[Test]
    public function inactive_income_category_cannot_be_used(): void
    {
        $admin = User::factory()->create([
            'role' => 'master_admin',
        ]);

        $category = IncomeCategory::create([
            'name' => 'Inactive',
            'description' => null,
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.income.store'), [
            'income_category_id' => $category->id,
            'title' => 'Blocked income',
            'amount' => 120,
            'income_date' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('income_category_id');
    }

    #[Test]
    public function master_admin_can_create_income_and_totals_are_correct(): void
    {
        $admin = User::factory()->create([
            'role' => 'master_admin',
        ]);

        $category = IncomeCategory::create([
            'name' => 'Services',
            'description' => null,
            'status' => 'active',
        ]);

        $this->actingAs($admin)->post(route('admin.income.store'), [
            'income_category_id' => $category->id,
            'title' => 'Income 1',
            'amount' => 100,
            'income_date' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('admin.income.store'), [
            'income_category_id' => $category->id,
            'title' => 'Income 2',
            'amount' => 200,
            'income_date' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $response = $this->actingAs($admin)->get(route('admin.income.index', [
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

        $response->assertOk();
        $props = $this->inertiaProps($response);
        $incomes = collect((array) ($props['incomes'] ?? []));
        $total = (float) $incomes->sum(function (array $row) {
            $display = (string) ($row['amount_display'] ?? '');
            preg_match('/-?\d+(?:\.\d+)?/', $display, $matches);

            return isset($matches[0]) ? (float) $matches[0] : 0.0;
        });

        $this->assertTrue(abs($total - 300.0) < 0.01);
    }

    #[Test]
    public function income_entries_include_manual_and_system_sources(): void
    {
        $creator = User::factory()->create([
            'role' => 'master_admin',
        ]);

        $category = IncomeCategory::create([
            'name' => 'Manual',
            'description' => null,
            'status' => 'active',
        ]);

        Income::create([
            'income_category_id' => $category->id,
            'title' => 'Manual income',
            'amount' => 100,
            'income_date' => now()->toDateString(),
            'created_by' => $creator->id,
        ]);

        AccountingEntry::create([
            'entry_date' => now()->toDateString(),
            'type' => 'payment',
            'amount' => 250,
            'currency' => 'BDT',
            'description' => 'System payment',
        ]);

        $entries = app(IncomeEntryService::class)->entries([
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'sources' => ['manual', 'system'],
        ]);

        $this->assertSame(2, $entries->count());
        $this->assertNotNull($entries->firstWhere('source_type', 'manual'));
        $this->assertNotNull($entries->firstWhere('source_type', 'system'));
    }

    #[Test]
    public function tax_settings_apply_exclusive_mode_to_invoices(): void
    {
        $rate = TaxRate::create([
            'name' => 'VAT',
            'rate_percent' => 15,
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
            'is_active' => true,
        ]);

        $settings = TaxSetting::current();
        $settings->update([
            'enabled' => true,
            'tax_mode_default' => 'exclusive',
            'default_tax_rate_id' => $rate->id,
            'invoice_tax_label' => 'Tax',
            'invoice_tax_note_template' => 'Tax ({rate}%)',
        ]);

        $customer = Customer::create([
            'name' => 'Tax Client',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-1001',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'subtotal' => 100,
            'late_fee' => 0,
            'total' => 100,
            'currency' => 'BDT',
        ]);

        $invoice = app(InvoiceTaxService::class)->applyToInvoice($invoice);

        $this->assertSame('exclusive', $invoice->tax_mode);
        $this->assertEqualsWithDelta(15.0, (float) $invoice->tax_rate_percent, 0.01);
        $this->assertEqualsWithDelta(15.0, (float) $invoice->tax_amount, 0.01);
        $this->assertEqualsWithDelta(115.0, (float) $invoice->total, 0.01);
        $this->assertSame('Tax (15%)', $settings->renderNote($invoice->tax_rate_percent));
    }

    #[Test]
    public function tax_settings_apply_inclusive_mode_to_invoices(): void
    {
        $rate = TaxRate::create([
            'name' => 'VAT',
            'rate_percent' => 15,
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
            'is_active' => true,
        ]);

        $settings = TaxSetting::current();
        $settings->update([
            'enabled' => true,
            'tax_mode_default' => 'inclusive',
            'default_tax_rate_id' => $rate->id,
            'invoice_tax_label' => 'Tax',
            'invoice_tax_note_template' => 'Tax ({rate}%)',
        ]);

        $customer = Customer::create([
            'name' => 'Inclusive Client',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-1002',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'subtotal' => 100,
            'late_fee' => 0,
            'total' => 100,
            'currency' => 'BDT',
        ]);

        $invoice = app(InvoiceTaxService::class)->applyToInvoice($invoice);
        $expectedTax = round(100 * (15 / (100 + 15)), 2);

        $this->assertSame('inclusive', $invoice->tax_mode);
        $this->assertEqualsWithDelta($expectedTax, (float) $invoice->tax_amount, 0.01);
        $this->assertEqualsWithDelta(100.0, (float) $invoice->total, 0.01);
    }

    #[Test]
    public function finance_reports_aggregate_income_and_expense_sources(): void
    {
        $admin = User::factory()->create([
            'role' => 'master_admin',
        ]);

        $incomeCategory = IncomeCategory::create([
            'name' => 'Income',
            'description' => null,
            'status' => 'active',
        ]);

        Income::create([
            'income_category_id' => $incomeCategory->id,
            'title' => 'Manual income',
            'amount' => 100,
            'income_date' => now()->toDateString(),
            'created_by' => $admin->id,
        ]);

        AccountingEntry::create([
            'entry_date' => now()->toDateString(),
            'type' => 'payment',
            'amount' => 400,
            'currency' => 'BDT',
            'description' => 'Payment',
        ]);

        $expenseCategory = ExpenseCategory::create([
            'name' => 'Operations',
            'description' => null,
            'status' => 'active',
        ]);

        Expense::create([
            'category_id' => $expenseCategory->id,
            'title' => 'Hosting',
            'amount' => 200,
            'expense_date' => now()->toDateString(),
            'type' => 'one_time',
            'created_by' => $admin->id,
        ]);

        $employee = Employee::create([
            'name' => 'Payroll Employee',
            'email' => 'payroll@example.com',
            'employment_type' => 'full_time',
            'work_mode' => 'remote',
            'join_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $period = PayrollPeriod::create([
            'period_key' => '2026-04',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'status' => 'paid',
        ]);

        PayrollItem::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'status' => 'paid',
            'pay_type' => 'monthly',
            'currency' => 'BDT',
            'base_pay' => 300,
            'gross_pay' => 300,
            'net_pay' => 300,
            'paid_at' => now(),
        ]);

        PayrollItem::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'status' => 'draft',
            'pay_type' => 'monthly',
            'currency' => 'BDT',
            'base_pay' => 500,
            'gross_pay' => 500,
            'net_pay' => 500,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.finance.reports.index', [
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

        $response->assertOk();
        $props = $this->inertiaProps($response);
        $summary = (array) ($props['summary'] ?? []);

        $this->assertTrue(abs(((float) ($summary['total_income'] ?? 0)) - 500.0) < 0.01);
        $this->assertTrue(abs(((float) ($summary['total_expense'] ?? 0)) - 500.0) < 0.01);
        $this->assertTrue(abs(((float) ($summary['received_income'] ?? 0)) - 400.0) < 0.01);
        $this->assertTrue(abs(((float) ($summary['payout_expense'] ?? 0)) - 300.0) < 0.01);
        $this->assertTrue(abs(((float) ($summary['net_cashflow'] ?? 0)) - 100.0) < 0.01);
    }

    #[Test]
    public function non_master_admin_cannot_access_finance_routes(): void
    {
        $user = User::factory()->create([
            'role' => 'sub_admin',
        ]);

        $this->actingAs($user)
            ->get(route('admin.income.index'))
            ->assertStatus(403);

        $this->actingAs($user)
            ->get(route('admin.finance.tax.index'))
            ->assertStatus(403);

        $this->actingAs($user)
            ->get(route('admin.finance.reports.index'))
            ->assertStatus(403);
    }

    private function inertiaProps(TestResponse $response): array
    {
        preg_match('/data-page="([^"]+)"/', (string) $response->getContent(), $matches);

        $this->assertArrayHasKey(1, $matches);

        $decoded = json_decode(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'), true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('props', $decoded);

        return (array) $decoded['props'];
    }
}
