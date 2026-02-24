<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubscriptionUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function subscriptions_index_is_inertia_when_legacy_flag_is_off(): void
    {
        config()->set('features.admin_subscriptions_index', false);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.subscriptions.index'));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Admin\\/Subscriptions\\/Index', false);
    }

    #[Test]
    public function subscriptions_index_remains_inertia_when_legacy_flag_is_on(): void
    {
        config()->set('features.admin_subscriptions_index', true);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.subscriptions.index'));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Admin\\/Subscriptions\\/Index', false);
    }

    #[Test]
    public function subscriptions_index_permission_guard_remains_forbidden_for_client_role_with_or_without_legacy_flag(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        config()->set('features.admin_subscriptions_index', false);
        $this->actingAs($client)
            ->get(route('admin.subscriptions.index'))
            ->assertForbidden();

        config()->set('features.admin_subscriptions_index', true);
        $this->actingAs($client)
            ->get(route('admin.subscriptions.index'))
            ->assertForbidden();
    }
}
