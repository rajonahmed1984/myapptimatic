<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\IncomeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IncomeUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function income_index_is_inertia_when_legacy_flag_is_off(): void
    {
        config()->set('features.admin_income_index', false);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.income.index'));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Admin\\/Income\\/Index', false);
    }

    #[Test]
    public function income_index_remains_inertia_when_legacy_flag_is_on(): void
    {
        config()->set('features.admin_income_index', true);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.income.index'));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Admin\\/Income\\/Index', false);
    }

    #[Test]
    public function income_index_permission_guard_remains_forbidden_for_client_role_with_or_without_legacy_flag(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        config()->set('features.admin_income_index', false);
        $this->actingAs($client)
            ->get(route('admin.income.index'))
            ->assertForbidden();

        config()->set('features.admin_income_index', true);
        $this->actingAs($client)
            ->get(route('admin.income.index'))
            ->assertForbidden();
    }

    #[Test]
    public function income_create_page_renders_inertia_for_admin_and_forbidden_for_client(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        IncomeCategory::query()->create([
            'name' => 'Services',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.income.create'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Income\\/Create', false);

        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        $this->actingAs($client)
            ->get(route('admin.income.create'))
            ->assertForbidden();
    }

    #[Test]
    public function income_store_keeps_redirect_and_validation_contracts(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.income.create'))
            ->post(route('admin.income.store'), [])
            ->assertRedirect(route('admin.income.create'))
            ->assertSessionHasErrors([
                'income_category_id',
                'title',
                'amount',
                'income_date',
            ]);

        $category = IncomeCategory::query()->create([
            'name' => 'Services',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.income.store'), [
                'income_category_id' => $category->id,
                'title' => 'Setup fee',
                'amount' => '120.50',
                'income_date' => now()->toDateString(),
                'notes' => 'Contract setup',
            ])
            ->assertRedirect(route('admin.income.index'))
            ->assertSessionHas('status', 'Income recorded.');
    }
}
