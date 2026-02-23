<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserActivitySummaryUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_activity_summary_uses_blade_when_react_flag_is_off(): void
    {
        config()->set('features.admin_users_activity_summary_index', false);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.activity-summary'));

        $response->assertOk();
        $response->assertViewIs('admin.users.activity-summary');
    }

    #[Test]
    public function user_activity_summary_uses_inertia_when_react_flag_is_on(): void
    {
        config()->set('features.admin_users_activity_summary_index', true);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.activity-summary'));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Admin\\/Users\\/ActivitySummary\\/Index', false);
    }

    #[Test]
    public function user_activity_summary_permission_guard_remains_forbidden_for_client_role_with_flag_on_and_off(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        config()->set('features.admin_users_activity_summary_index', false);
        $this->actingAs($client)
            ->get(route('admin.users.activity-summary'))
            ->assertForbidden();

        config()->set('features.admin_users_activity_summary_index', true);
        $this->actingAs($client)
            ->get(route('admin.users.activity-summary'))
            ->assertForbidden();
    }
}
