<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CarrotHostIncomeUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function carrothost_index_renders_direct_inertia_for_admin(): void
    {
        $this->disableWhmcsForTest();
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $response = $this->actingAs($admin)->get(route('admin.income.carrothost', ['month' => '2026-01']));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Admin\\/Income\\/CarrotHost', false);
    }

    #[Test]
    public function carrothost_routes_keep_permission_guard_for_client_role(): void
    {
        $this->disableWhmcsForTest();
        $client = User::factory()->create(['role' => Role::CLIENT]);

        $this->actingAs($client)
            ->get(route('admin.income.carrothost'))
            ->assertForbidden();

        $this->actingAs($client)
            ->post(route('admin.income.carrothost.sync'))
            ->assertForbidden();
    }

    #[Test]
    public function carrothost_sync_keeps_redirect_contract_for_web_requests(): void
    {
        $this->disableWhmcsForTest();
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $this->actingAs($admin)
            ->post(route('admin.income.carrothost.sync'), ['month' => '2026-01'])
            ->assertRedirect(route('admin.income.carrothost', ['month' => '2026-01']))
            ->assertSessionHas('status', 'CarrotHost data synced.');
    }

    #[Test]
    public function carrothost_sync_keeps_json_contract_for_ajax_requests(): void
    {
        $this->disableWhmcsForTest();
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.income.carrothost.sync'), ['month' => '2026-01']);

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'message' => 'CarrotHost data synced.',
            'month' => '2026-01',
        ]);
        $response->assertJsonStructure([
            'warnings',
            'range' => ['start', 'end'],
        ]);
    }

    private function disableWhmcsForTest(): void
    {
        config()->set('whmcs.url', '');
        config()->set('whmcs.api_url', '');
        config()->set('whmcs.username', '');
        config()->set('whmcs.identifier', '');
        config()->set('whmcs.secret', '');
    }
}
