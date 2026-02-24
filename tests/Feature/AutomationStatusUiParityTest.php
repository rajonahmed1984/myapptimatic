<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AutomationStatusUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function automation_status_is_inertia_when_legacy_flag_is_off(): void
    {
        config()->set('features.admin_automation_status_index', false);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.automation-status'));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Admin\\/AutomationStatus\\/Index', false);
    }

    #[Test]
    public function automation_status_remains_inertia_when_legacy_flag_is_on(): void
    {
        config()->set('features.admin_automation_status_index', true);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.automation-status'));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Admin\\/AutomationStatus\\/Index', false);
    }

    #[Test]
    public function automation_status_permission_guard_remains_forbidden_for_client_role_with_or_without_legacy_flag(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        config()->set('features.admin_automation_status_index', false);
        $this->actingAs($client)
            ->get(route('admin.automation-status'))
            ->assertForbidden();

        config()->set('features.admin_automation_status_index', true);
        $this->actingAs($client)
            ->get(route('admin.automation-status'))
            ->assertForbidden();
    }
}
