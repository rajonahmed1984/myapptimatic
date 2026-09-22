<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CategoryRoutesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_expense_categories_url_redirects_to_expenses_categories_index(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get('/admin/expense-categories');

        $response->assertRedirect(route('admin.expenses.categories.index'));

        $follow = $this->actingAs($admin)->get(route('admin.expenses.categories.index'));
        $follow->assertOk();
        $follow->assertSee('Admin\\/Expenses\\/Categories\\/Index', false);
    }

    #[Test]
    public function admin_income_categories_url_redirects_to_income_categories_index(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get('/admin/income-categories');

        $response->assertRedirect(route('admin.income.categories.index'));

        $follow = $this->actingAs($admin)->get(route('admin.income.categories.index'));
        $follow->assertOk();
        $follow->assertSee('Admin\\/Income\\/Categories\\/Index', false);
    }

    #[Test]
    public function category_redirects_preserve_query_parameters(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get('/admin/expense-categories?edit=99');
        $response->assertRedirect(route('admin.expenses.categories.index', ['edit' => '99']));

        $incomeResponse = $this->actingAs($admin)->get('/admin/income-categories?edit=42');
        $incomeResponse->assertRedirect(route('admin.income.categories.index', ['edit' => '42']));
    }
}
