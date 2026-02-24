<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminLegacyBridgeUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function legacy_admin_blade_pages_are_bridged_to_inertia_component(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.projects.create'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Legacy\\/HtmlPage', false);
    }
}
