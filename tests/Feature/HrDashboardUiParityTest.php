<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HrDashboardUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function hr_dashboard_renders_direct_inertia_component_for_admin(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.hr.dashboard'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Hr\\/Dashboard', false);
    }

    #[Test]
    public function hr_dashboard_remains_forbidden_for_client_role(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        $this->actingAs($client)
            ->get(route('admin.hr.dashboard'))
            ->assertForbidden();
    }
}
