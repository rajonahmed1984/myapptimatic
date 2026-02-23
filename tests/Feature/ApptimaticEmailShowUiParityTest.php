<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApptimaticEmailShowUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function apptimatic_email_show_uses_blade_when_react_flag_is_off(): void
    {
        config()->set('features.admin_apptimatic_email_show', false);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.apptimatic-email.show', ['message' => 'm-1001']));

        $response->assertOk();
        $response->assertViewIs('admin.apptimatic-email.show');
    }

    #[Test]
    public function apptimatic_email_show_uses_inertia_when_react_flag_is_on(): void
    {
        config()->set('features.admin_apptimatic_email_show', true);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.apptimatic-email.show', ['message' => 'm-1001']));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Admin\\/ApptimaticEmail\\/Inbox', false);
    }

    #[Test]
    public function apptimatic_email_show_permission_guard_remains_forbidden_for_client_role_with_flag_on_and_off(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        config()->set('features.admin_apptimatic_email_show', false);
        $this->actingAs($client)
            ->get(route('admin.apptimatic-email.show', ['message' => 'm-1001']))
            ->assertForbidden();

        config()->set('features.admin_apptimatic_email_show', true);
        $this->actingAs($client)
            ->get(route('admin.apptimatic-email.show', ['message' => 'm-1001']))
            ->assertForbidden();
    }
}
