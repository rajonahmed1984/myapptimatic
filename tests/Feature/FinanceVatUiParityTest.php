<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinanceVatUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function finance_vat_index_is_inertia_when_legacy_flag_is_off(): void
    {
        config()->set('features.admin_finance_vat_index', false);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.finance.vat.index'));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Admin\\/Finance\\/Vat\\/Index', false);
        $response->assertSee('VAT Settings');
        $response->assertDontSee('Tax Settings');
    }

    #[Test]
    public function finance_vat_index_remains_inertia_when_legacy_flag_is_on(): void
    {
        config()->set('features.admin_finance_vat_index', true);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.finance.vat.index'));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Admin\\/Finance\\/Vat\\/Index', false);
    }

    #[Test]
    public function finance_vat_index_permission_guard_remains_forbidden_for_client_role_with_or_without_legacy_flag(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        config()->set('features.admin_finance_vat_index', false);
        $this->actingAs($client)
            ->get(route('admin.finance.vat.index'))
            ->assertForbidden();

        config()->set('features.admin_finance_vat_index', true);
        $this->actingAs($client)
            ->get(route('admin.finance.vat.index'))
            ->assertForbidden();
    }
}
