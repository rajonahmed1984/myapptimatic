<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseInvoice;
use App\Models\ExpenseInvoicePayment;
use App\Models\RecurringExpense;
use App\Models\RecurringExpenseAdvance;
use App\Models\User;
use App\Services\RecurringExpenseGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecurringExpenseGeneratorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function recurring_expense_generation_is_idempotent(): void
    {
        $admin = User::factory()->create(['role' => 'master_admin']);

        $category = ExpenseCategory::create([
            'name' => 'Subscriptions',
            'description' => 'Recurring subscriptions',
            'status' => 'active',
        ]);

        $recurring = RecurringExpense::create([
            'category_id' => $category->id,
            'title' => 'Monthly Hosting',
            'amount' => 250,
            'recurrence_type' => 'monthly',
            'recurrence_interval' => 1,
            'start_date' => '2026-04-01',
            'next_run_date' => '2026-04-01',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $generator = app(RecurringExpenseGenerator::class);
        $asOf = Carbon::parse('2026-04-01');

        $generator->generate($asOf);
        $this->assertSame(1, Expense::count());

        $generator->generate($asOf);
        $this->assertSame(1, Expense::count());

        $recurring->refresh();
        $this->assertSame('2026-05-01', $recurring->next_run_date?->toDateString());
    }

    #[Test]
    public function recurring_generation_auto_applies_advance_until_balance_is_used(): void
    {
        Carbon::setTestNow('2026-05-02 09:00:00');

        $admin = User::factory()->create(['role' => 'master_admin']);

        $category = ExpenseCategory::create([
            'name' => 'Cloud',
            'description' => 'Cloud subscriptions',
            'status' => 'active',
        ]);

        $recurring = RecurringExpense::create([
            'category_id' => $category->id,
            'title' => 'Cloud Server',
            'amount' => 250,
            'recurrence_type' => 'monthly',
            'recurrence_interval' => 1,
            'start_date' => '2026-04-01',
            'next_run_date' => '2026-04-01',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        RecurringExpenseAdvance::create([
            'recurring_expense_id' => $recurring->id,
            'payment_method' => 'bank',
            'amount' => 300,
            'paid_at' => '2026-03-28',
            'created_by' => $admin->id,
        ]);

        $generator = app(RecurringExpenseGenerator::class);
        $generator->generate(Carbon::parse('2026-05-01'));

        $this->assertSame(2, Expense::where('recurring_expense_id', $recurring->id)->count());
        $this->assertSame(2, ExpenseInvoice::whereHas('expense', fn ($q) => $q->where('recurring_expense_id', $recurring->id))->count());
        $this->assertSame(2, ExpenseInvoicePayment::where('payment_method', 'advance')->count());

        $payments = ExpenseInvoicePayment::query()
            ->where('payment_method', 'advance')
            ->orderBy('paid_at')
            ->orderBy('id')
            ->pluck('amount')
            ->map(fn ($value) => round((float) $value, 2))
            ->all();

        $this->assertSame([250.0, 50.0], $payments);

        $paidInvoices = ExpenseInvoice::query()
            ->whereHas('expense', fn ($q) => $q->where('recurring_expense_id', $recurring->id))
            ->where('status', 'paid')
            ->count();

        $this->assertSame(1, $paidInvoices);

        Carbon::setTestNow();
    }
}
