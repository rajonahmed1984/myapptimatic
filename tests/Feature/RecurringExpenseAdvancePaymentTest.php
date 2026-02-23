<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseInvoice;
use App\Models\ExpenseInvoicePayment;
use App\Models\RecurringExpense;
use App\Models\RecurringExpenseAdvance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecurringExpenseAdvancePaymentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function recurring_invoice_advance_payment_is_capped_to_available_balance(): void
    {
        $admin = User::factory()->create(['role' => 'master_admin']);

        $category = ExpenseCategory::create([
            'name' => 'Subscriptions',
            'description' => null,
            'status' => 'active',
        ]);

        $recurring = RecurringExpense::create([
            'category_id' => $category->id,
            'title' => 'Hosting',
            'amount' => 150,
            'recurrence_type' => 'monthly',
            'recurrence_interval' => 1,
            'start_date' => '2026-04-01',
            'next_run_date' => '2026-05-01',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $expense = Expense::create([
            'category_id' => $category->id,
            'recurring_expense_id' => $recurring->id,
            'title' => 'Hosting Apr',
            'amount' => 150,
            'expense_date' => '2026-04-01',
            'type' => 'recurring',
            'created_by' => $admin->id,
        ]);

        $invoice = ExpenseInvoice::create([
            'expense_id' => $expense->id,
            'source_type' => 'expense',
            'source_id' => $expense->id,
            'expense_type' => 'recurring',
            'invoice_no' => 'EXP-2026-0001',
            'status' => 'unpaid',
            'invoice_date' => '2026-04-01',
            'due_date' => '2026-04-01',
            'amount' => 150,
            'currency' => 'BDT',
            'created_by' => $admin->id,
        ]);

        RecurringExpenseAdvance::create([
            'recurring_expense_id' => $recurring->id,
            'payment_method' => 'bank',
            'amount' => 100,
            'paid_at' => '2026-03-28',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.expenses.invoices.pay', $invoice), [
            'payment_method' => 'advance',
            'payment_type' => 'full',
            'amount' => 150,
            'paid_at' => '2026-04-01',
        ]);

        $response->assertSessionHasNoErrors();

        $payment = ExpenseInvoicePayment::query()->first();
        $this->assertNotNull($payment);
        $this->assertSame('advance', $payment->payment_method);
        $this->assertSame('partial', $payment->payment_type);
        $this->assertSame(100.0, round((float) $payment->amount, 2));

        $invoice->refresh();
        $this->assertNotSame('paid', $invoice->status);
    }

    #[Test]
    public function recurring_invoice_advance_payment_fails_when_no_advance_balance_exists(): void
    {
        $admin = User::factory()->create(['role' => 'master_admin']);

        $category = ExpenseCategory::create([
            'name' => 'Subscriptions',
            'description' => null,
            'status' => 'active',
        ]);

        $recurring = RecurringExpense::create([
            'category_id' => $category->id,
            'title' => 'Hosting',
            'amount' => 80,
            'recurrence_type' => 'monthly',
            'recurrence_interval' => 1,
            'start_date' => '2026-04-01',
            'next_run_date' => '2026-05-01',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $expense = Expense::create([
            'category_id' => $category->id,
            'recurring_expense_id' => $recurring->id,
            'title' => 'Hosting Apr',
            'amount' => 80,
            'expense_date' => '2026-04-01',
            'type' => 'recurring',
            'created_by' => $admin->id,
        ]);

        $invoice = ExpenseInvoice::create([
            'expense_id' => $expense->id,
            'source_type' => 'expense',
            'source_id' => $expense->id,
            'expense_type' => 'recurring',
            'invoice_no' => 'EXP-2026-0002',
            'status' => 'unpaid',
            'invoice_date' => '2026-04-01',
            'due_date' => '2026-04-01',
            'amount' => 80,
            'currency' => 'BDT',
            'created_by' => $admin->id,
        ]);

        $response = $this->from(route('admin.expenses.recurring.show', $recurring))
            ->actingAs($admin)
            ->post(route('admin.expenses.invoices.pay', $invoice), [
                'payment_method' => 'advance',
                'payment_type' => 'full',
                'amount' => 80,
                'paid_at' => '2026-04-01',
            ]);

        $response->assertRedirect(route('admin.expenses.recurring.show', $recurring));
        $response->assertSessionHasErrors('expense_invoice');
        $this->assertSame(0, ExpenseInvoicePayment::count());
    }
}
