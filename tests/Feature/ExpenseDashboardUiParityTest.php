<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExpenseDashboardUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function expense_dashboard_renders_direct_inertia_component_for_master_admin(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $this->actingAs($admin)
            ->get(route('admin.expenses.dashboard'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Expenses\\/Dashboard', false);
    }

    #[Test]
    public function expense_dashboard_remains_forbidden_for_sub_admin(): void
    {
        $subAdmin = User::factory()->create(['role' => Role::SUB_ADMIN]);

        $this->actingAs($subAdmin)
            ->get(route('admin.expenses.dashboard'))
            ->assertForbidden();
    }
}
