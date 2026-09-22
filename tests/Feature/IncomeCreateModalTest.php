<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IncomeCreateModalTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_income_index_provides_categories_and_modal_support(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        IncomeCategory::create([
            'name' => 'Consulting',
            'status' => 'active',
        ]);
        IncomeCategory::create([
            'name' => 'Old Category',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.income.index'));

        $response->assertOk();
        $response->assertSee('Admin\\/Income\\/Index', false);
        $response->assertSee('Consulting', false);
        $response->assertDontSee('Old Category', false);
    }

    #[Test]
    public function admin_income_create_url_redirects_to_income_index_with_create_param(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.income.create'));

        $response->assertRedirect(route('admin.income.index', ['create' => 1]));
    }

    #[Test]
    public function admin_can_record_income_via_store_endpoint(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $category = IncomeCategory::create([
            'name' => 'Web App Maintenance',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.income.store'), [
            'income_category_id' => $category->id,
            'title' => 'Monthly Retainer',
            'amount' => '450.00',
            'income_date' => '2026-09-23',
            'notes' => 'Direct bank transfer',
        ]);

        $response->assertRedirect(route('admin.income.index'));
        $response->assertSessionHas('status', 'Income recorded.');

        $this->assertDatabaseHas('incomes', [
            'income_category_id' => $category->id,
            'title' => 'Monthly Retainer',
            'amount' => 450.00,
            'notes' => 'Direct bank transfer',
            'created_by' => $admin->id,
        ]);
    }
}
