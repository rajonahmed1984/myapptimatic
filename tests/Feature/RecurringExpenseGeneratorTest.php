<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\RecurringExpense;
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
}
