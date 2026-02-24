<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlanUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function plans_index_is_inertia_when_legacy_flag_is_off(): void
    {
        config()->set('features.admin_plans_index', false);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.plans.index'));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Admin\\/Plans\\/Index', false);
    }

    #[Test]
    public function plans_index_remains_inertia_when_legacy_flag_is_on(): void
    {
        config()->set('features.admin_plans_index', true);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.plans.index'));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Admin\\/Plans\\/Index', false);
    }

    #[Test]
    public function plans_index_permission_guard_remains_forbidden_for_client_role_with_or_without_legacy_flag(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        config()->set('features.admin_plans_index', false);
        $this->actingAs($client)
            ->get(route('admin.plans.index'))
            ->assertForbidden();

        config()->set('features.admin_plans_index', true);
        $this->actingAs($client)
            ->get(route('admin.plans.index'))
            ->assertForbidden();
    }
}
