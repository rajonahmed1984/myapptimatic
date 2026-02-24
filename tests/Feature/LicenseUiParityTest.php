<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LicenseUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function licenses_index_is_inertia_when_legacy_flag_is_off(): void
    {
        config()->set('features.admin_licenses_index', false);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.licenses.index'));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Admin\\/Licenses\\/Index', false);
    }

    #[Test]
    public function licenses_index_remains_inertia_when_legacy_flag_is_on(): void
    {
        config()->set('features.admin_licenses_index', true);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.licenses.index'));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Admin\\/Licenses\\/Index', false);
    }

    #[Test]
    public function licenses_index_permission_guard_remains_forbidden_for_client_role_with_or_without_legacy_flag(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        config()->set('features.admin_licenses_index', false);
        $this->actingAs($client)
            ->get(route('admin.licenses.index'))
            ->assertForbidden();

        config()->set('features.admin_licenses_index', true);
        $this->actingAs($client)
            ->get(route('admin.licenses.index'))
            ->assertForbidden();
    }
}
