<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\ExpenseCategory;
use App\Models\RecurringExpense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecurringExpenseUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function recurring_index_uses_blade_when_react_flag_is_off(): void
    {
        config()->set('features.admin_expenses_recurring_index', false);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.expenses.recurring.index'));

        $response->assertOk();
        $response->assertViewIs('admin.expenses.recurring.index');
    }

    #[Test]
    public function recurring_index_uses_inertia_when_react_flag_is_on(): void
    {
        config()->set('features.admin_expenses_recurring_index', true);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.expenses.recurring.index'));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Admin\\/Expenses\\/Recurring\\/Index', false);
    }

    #[Test]
    public function recurring_index_permission_guard_remains_forbidden_for_client_role_with_flag_on_and_off(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        config()->set('features.admin_expenses_recurring_index', false);
        $this->actingAs($client)
            ->get(route('admin.expenses.recurring.index'))
            ->assertForbidden();

        config()->set('features.admin_expenses_recurring_index', true);
        $this->actingAs($client)
            ->get(route('admin.expenses.recurring.index'))
            ->assertForbidden();
    }

    #[Test]
    public function recurring_show_uses_blade_when_react_flag_is_off(): void
    {
        config()->set('features.admin_expenses_recurring_show', false);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);
        $recurring = $this->createRecurringExpense($admin);

        $response = $this->actingAs($admin)->get(route('admin.expenses.recurring.show', $recurring));

        $response->assertOk();
        $response->assertViewIs('admin.expenses.recurring.show');
    }

    #[Test]
    public function recurring_show_uses_inertia_when_react_flag_is_on(): void
    {
        config()->set('features.admin_expenses_recurring_show', true);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);
        $recurring = $this->createRecurringExpense($admin);

        $response = $this->actingAs($admin)->get(route('admin.expenses.recurring.show', $recurring));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Admin\\/Expenses\\/Recurring\\/Show', false);
    }

    #[Test]
    public function recurring_show_permission_guard_remains_forbidden_for_client_role_with_flag_on_and_off(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);
        $recurring = $this->createRecurringExpense($admin);

        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        config()->set('features.admin_expenses_recurring_show', false);
        $this->actingAs($client)
            ->get(route('admin.expenses.recurring.show', $recurring))
            ->assertForbidden();

        config()->set('features.admin_expenses_recurring_show', true);
        $this->actingAs($client)
            ->get(route('admin.expenses.recurring.show', $recurring))
            ->assertForbidden();
    }

    private function createRecurringExpense(User $admin): RecurringExpense
    {
        $category = ExpenseCategory::query()->create([
            'name' => 'UI Parity Category '.uniqid(),
            'description' => null,
            'status' => 'active',
        ]);

        return RecurringExpense::query()->create([
            'category_id' => $category->id,
            'title' => 'UI Parity Recurring '.uniqid(),
            'amount' => 150,
            'recurrence_type' => 'monthly',
            'recurrence_interval' => 1,
            'start_date' => '2026-04-01',
            'next_run_date' => '2026-04-01',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);
    }
}
