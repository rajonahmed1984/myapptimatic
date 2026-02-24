<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExpensesOneTimeUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function one_time_create_and_edit_routes_render_inertia_payload_for_master_admin(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $category = ExpenseCategory::query()->create([
            'name' => 'Ops',
            'status' => 'active',
        ]);
        $expense = Expense::query()->create([
            'category_id' => $category->id,
            'title' => 'Laptop bag',
            'amount' => 99.99,
            'expense_date' => now()->toDateString(),
            'type' => 'one_time',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.expenses.create'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Expenses\\/Create', false);

        $this->actingAs($admin)
            ->get(route('admin.expenses.edit', $expense))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Expenses\\/Edit', false);
    }

    #[Test]
    public function one_time_expense_routes_remain_forbidden_for_client_role(): void
    {
        $client = User::factory()->create(['role' => Role::CLIENT]);

        $category = ExpenseCategory::query()->create([
            'name' => 'Ops',
            'status' => 'active',
        ]);
        $expense = Expense::query()->create([
            'category_id' => $category->id,
            'title' => 'Blocked expense',
            'amount' => 50,
            'expense_date' => now()->toDateString(),
            'type' => 'one_time',
            'created_by' => User::factory()->create(['role' => Role::MASTER_ADMIN])->id,
        ]);

        $this->actingAs($client)->get(route('admin.expenses.create'))->assertForbidden();
        $this->actingAs($client)->get(route('admin.expenses.edit', $expense))->assertForbidden();
        $this->actingAs($client)->post(route('admin.expenses.store'), [])->assertForbidden();
    }

    #[Test]
    public function one_time_store_validation_and_success_contracts_are_preserved(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $category = ExpenseCategory::query()->create([
            'name' => 'Operations',
            'status' => 'active',
        ]);

        $createUrl = route('admin.expenses.create');

        $this->actingAs($admin)->from($createUrl)
            ->post(route('admin.expenses.store'), [])
            ->assertRedirect($createUrl)
            ->assertSessionHasErrors(['category_id', 'title', 'amount', 'expense_date']);

        $this->actingAs($admin)->from($createUrl)
            ->post(route('admin.expenses.store'), [
                'category_id' => $category->id,
                'title' => 'Domain renewal',
                'amount' => 12.5,
                'expense_date' => now()->toDateString(),
                'notes' => 'Annual renewal',
            ])
            ->assertRedirect(route('admin.expenses.index'))
            ->assertSessionHas('status', 'Expense recorded.');
    }

    #[Test]
    public function one_time_update_and_destroy_contracts_are_preserved(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $category = ExpenseCategory::query()->create([
            'name' => 'Software',
            'status' => 'active',
        ]);
        $expense = Expense::query()->create([
            'category_id' => $category->id,
            'title' => 'Old title',
            'amount' => 100,
            'expense_date' => now()->subDay()->toDateString(),
            'type' => 'one_time',
            'created_by' => $admin->id,
        ]);

        $editUrl = route('admin.expenses.edit', $expense);

        $this->actingAs($admin)->from($editUrl)
            ->put(route('admin.expenses.update', $expense), [
                'category_id' => '',
                'title' => '',
                'amount' => '',
                'expense_date' => '',
            ])
            ->assertRedirect($editUrl)
            ->assertSessionHasErrors(['category_id', 'title', 'amount', 'expense_date']);

        $this->actingAs($admin)
            ->put(route('admin.expenses.update', $expense), [
                'category_id' => $category->id,
                'title' => 'Updated title',
                'amount' => 120.75,
                'expense_date' => now()->toDateString(),
                'notes' => 'Updated notes',
            ])
            ->assertRedirect(route('admin.expenses.create'))
            ->assertSessionHas('status', 'Expense updated.');

        $this->actingAs($admin)
            ->delete(route('admin.expenses.destroy', $expense))
            ->assertRedirect(route('admin.expenses.create'))
            ->assertSessionHas('status', 'Expense deleted.');
    }
}
