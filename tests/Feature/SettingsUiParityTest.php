<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SettingsUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function settings_edit_renders_direct_inertia_for_admin(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $this->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Settings\\/Edit', false);
    }

    #[Test]
    public function settings_routes_keep_permission_guard_for_client_role(): void
    {
        $client = User::factory()->create(['role' => Role::CLIENT]);

        $this->actingAs($client)
            ->get(route('admin.settings.edit'))
            ->assertForbidden();

        $this->actingAs($client)
            ->put(route('admin.settings.update'), [])
            ->assertForbidden();
    }

    #[Test]
    public function settings_update_keeps_redirect_and_validation_contracts(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $this->actingAs($admin)
            ->from(route('admin.settings.edit', ['tab' => 'general']))
            ->put(route('admin.settings.update'), [])
            ->assertRedirect(route('admin.settings.edit', ['tab' => 'general']))
            ->assertSessionHasErrors([
                'company_name',
                'currency',
                'invoice_generation_days',
                'invoice_due_days',
                'date_format',
                'time_zone',
                'automation_time_of_day',
            ]);
    }
}
