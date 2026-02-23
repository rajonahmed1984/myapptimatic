<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentGatewayUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function payment_gateway_index_uses_blade_when_react_flag_is_off(): void
    {
        config()->set('features.admin_payment_gateways_index', false);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.payment-gateways.index'));

        $response->assertOk();
        $response->assertViewIs('admin.payment-gateways.index');
    }

    #[Test]
    public function payment_gateway_index_uses_inertia_when_react_flag_is_on(): void
    {
        config()->set('features.admin_payment_gateways_index', true);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.payment-gateways.index'));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Admin\\/PaymentGateways\\/Index', false);
    }

    #[Test]
    public function payment_gateway_index_permission_guard_remains_forbidden_for_client_role_with_flag_on_and_off(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        config()->set('features.admin_payment_gateways_index', false);
        $this->actingAs($client)
            ->get(route('admin.payment-gateways.index'))
            ->assertForbidden();

        config()->set('features.admin_payment_gateways_index', true);
        $this->actingAs($client)
            ->get(route('admin.payment-gateways.index'))
            ->assertForbidden();
    }
}
