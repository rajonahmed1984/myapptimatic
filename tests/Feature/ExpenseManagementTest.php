<?php

namespace Tests\Feature;

use App\Models\AccountingEntry;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseInvoice;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\ExpenseEntryService;
use App\Services\ExpenseInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExpenseManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function master_admin_can_create_manual_expense_with_invoice(): void
    {
        $admin = User::factory()->create([
            'role' => 'master_admin',
        ]);

        $category = ExpenseCategory::create([
            'name' => 'Office',
            'description' => 'Office expenses',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.expenses.store'), [
            'category_id' => $category->id,
            'title' => 'Office supplies',
            'amount' => 1200,
            'expense_date' => now()->toDateString(),
            'notes' => 'Paper and toner',
            'generate_invoice' => 1,
        ]);

        $response->assertRedirect(route('admin.expenses.index'));

        $expense = Expense::first();
        $this->assertNotNull($expense);

        $this->assertDatabaseHas('expense_invoices', [
            'source_type' => 'expense',
            'source_id' => $expense->id,
            'expense_id' => $expense->id,
        ]);
    }

    #[Test]
    public function inactive_category_cannot_be_used_for_expenses(): void
    {
        $admin = User::factory()->create([
            'role' => 'master_admin',
        ]);

        $category = ExpenseCategory::create([
            'name' => 'Deprecated',
            'description' => 'Inactive category',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.expenses.store'), [
            'category_id' => $category->id,
            'title' => 'Old expense',
            'amount' => 50,
            'expense_date' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('category_id');
    }

    #[Test]
    public function salary_items_are_included_in_expense_entries(): void
    {
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
            'base_pay' => 1500,
            'gross_pay' => 1500,
            'net_pay' => 1500,
            'paid_at' => now(),
        ]);

        $entries = app(ExpenseEntryService::class)->entries([
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'sources' => ['salary'],
        ]);

        $entry = $entries->firstWhere('source_type', 'payroll_item');
        $this->assertNotNull($entry);
        $this->assertSame('salary', $entry['expense_type']);
        $this->assertSame(1500.0, $entry['amount']);
        $this->assertSame('Payroll Employee', $entry['person_name']);
    }

    #[Test]
    public function expense_dashboard_totals_reflect_income_and_expenses(): void
    {
        $admin = User::factory()->create([
            'role' => 'master_admin',
        ]);

        $category = ExpenseCategory::create([
            'name' => 'Operations',
            'description' => null,
            'status' => 'active',
        ]);

        Expense::create([
            'category_id' => $category->id,
            'title' => 'Hosting',
            'amount' => 2000,
            'expense_date' => now()->toDateString(),
            'type' => 'one_time',
            'created_by' => $admin->id,
        ]);

        $employee = Employee::create([
            'name' => 'Payroll Employee',
            'email' => 'payroll2@example.com',
            'employment_type' => 'full_time',
            'work_mode' => 'remote',
            'join_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $period = PayrollPeriod::create([
            'period_key' => '2026-05',
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
            'base_pay' => 1000,
            'gross_pay' => 1000,
            'net_pay' => 1000,
            'paid_at' => now(),
        ]);

        AccountingEntry::create([
            'entry_date' => now()->toDateString(),
            'type' => 'payment',
            'amount' => 5000,
            'currency' => 'BDT',
            'description' => 'Client payment',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.expenses.dashboard', [
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]));
        $content = $response->getContent();

        $this->assertIsString($content);
        preg_match('/data-page="([^"]+)"/', $content, $matches);
        $payload = json_decode(html_entity_decode((string) ($matches[1] ?? ''), ENT_QUOTES), true);
        $props = Arr::get($payload, 'props', []);

        $this->assertTrue(abs(((float) ($props['expenseTotal'] ?? 0)) - 3000.0) < 0.01);
        $this->assertTrue(abs(((float) ($props['incomeReceived'] ?? 0)) - 5000.0) < 0.01);
        $this->assertTrue(abs(((float) ($props['payoutExpenseTotal'] ?? 0)) - 1000.0) < 0.01);
        $this->assertTrue(abs(((float) ($props['netIncome'] ?? 0)) - 2000.0) < 0.01);
        $this->assertTrue(abs(((float) ($props['netCashflow'] ?? 0)) - 4000.0) < 0.01);
    }

    #[Test]
    public function expense_invoice_generation_is_idempotent(): void
    {
        $admin = User::factory()->create([
            'role' => 'master_admin',
        ]);

        $employee = Employee::create([
            'name' => 'Payroll Employee',
            'email' => 'payroll3@example.com',
            'employment_type' => 'full_time',
            'work_mode' => 'remote',
            'join_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $period = PayrollPeriod::create([
            'period_key' => '2026-06',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'status' => 'paid',
        ]);

        $item = PayrollItem::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'status' => 'paid',
            'pay_type' => 'monthly',
            'currency' => 'BDT',
            'base_pay' => 900,
            'gross_pay' => 900,
            'net_pay' => 900,
            'paid_at' => now(),
        ]);

        $service = app(ExpenseInvoiceService::class);
        $first = $service->createForSource('payroll_item', $item->id, 'salary', 900, now(), $admin->id);
        $second = $service->createForSource('payroll_item', $item->id, 'salary', 900, now(), $admin->id);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ExpenseInvoice::count());
    }

    #[Test]
    public function non_master_admin_cannot_access_expenses(): void
    {
        $user = User::factory()->create([
            'role' => 'sub_admin',
        ]);

        $this->actingAs($user)
            ->get(route('admin.expenses.index'))
            ->assertStatus(403);
    }
}
