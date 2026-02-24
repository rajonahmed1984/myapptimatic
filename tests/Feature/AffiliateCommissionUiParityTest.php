<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AffiliateCommissionUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function affiliate_commissions_index_is_inertia_when_legacy_flag_is_off(): void
    {
        config()->set('features.admin_affiliate_commissions_index', false);
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $response = $this->actingAs($admin)->get(route('admin.affiliates.commissions.index'));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Admin\\/Affiliates\\/Commissions\\/Index', false);
    }

    #[Test]
    public function affiliate_commissions_index_remains_inertia_when_legacy_flag_is_on(): void
    {
        config()->set('features.admin_affiliate_commissions_index', true);
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $response = $this->actingAs($admin)->get(route('admin.affiliates.commissions.index'));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Admin\\/Affiliates\\/Commissions\\/Index', false);
    }

    #[Test]
    public function affiliate_commissions_index_permission_guard_remains_forbidden_for_client_role_with_or_without_legacy_flag(): void
    {
        $client = User::factory()->create(['role' => Role::CLIENT]);

        config()->set('features.admin_affiliate_commissions_index', false);
        $this->actingAs($client)
            ->get(route('admin.affiliates.commissions.index'))
            ->assertForbidden();

        config()->set('features.admin_affiliate_commissions_index', true);
        $this->actingAs($client)
            ->get(route('admin.affiliates.commissions.index'))
            ->assertForbidden();
    }

    #[Test]
    public function approve_reject_and_bulk_actions_keep_redirect_and_data_contract_when_flag_toggles(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        [$affiliateA, $commissionA] = $this->seedPendingCommission('a');
        [, $commissionB] = $this->seedPendingCommission('b');

        config()->set('features.admin_affiliate_commissions_index', false);
        $this->actingAs($admin)
            ->from(route('admin.affiliates.commissions.index'))
            ->post(route('admin.affiliates.commissions.approve', $commissionA))
            ->assertRedirect(route('admin.affiliates.commissions.index'))
            ->assertSessionHas('status', 'Commission approved successfully.');
        $commissionA->refresh();
        $affiliateA->refresh();
        $this->assertSame('approved', $commissionA->status);
        $this->assertEquals(25.00, (float) $affiliateA->total_earned);

        config()->set('features.admin_affiliate_commissions_index', true);
        $this->actingAs($admin)
            ->from(route('admin.affiliates.commissions.index'))
            ->post(route('admin.affiliates.commissions.reject', $commissionB))
            ->assertRedirect(route('admin.affiliates.commissions.index'))
            ->assertSessionHas('status', 'Commission rejected.');
        $commissionB->refresh();
        $this->assertSame('cancelled', $commissionB->status);

        [, $commissionC] = $this->seedPendingCommission('c');
        [, $commissionD] = $this->seedPendingCommission('d');
        config()->set('features.admin_affiliate_commissions_index', false);
        $this->actingAs($admin)
            ->from(route('admin.affiliates.commissions.index'))
            ->post(route('admin.affiliates.commissions.bulk-approve'), [
                'commission_ids' => [$commissionC->id, $commissionD->id],
            ])
            ->assertRedirect(route('admin.affiliates.commissions.index'))
            ->assertSessionHas('status', '2 commission(s) approved.');

        $commissionC->refresh();
        $commissionD->refresh();
        $this->assertSame('approved', $commissionC->status);
        $this->assertSame('approved', $commissionD->status);
    }

    /**
     * @return array{Affiliate, AffiliateCommission}
     */
    private function seedPendingCommission(string $suffix): array
    {
        $customer = Customer::create([
            'name' => 'Affiliate Customer ' . strtoupper($suffix),
            'email' => 'affiliate-' . $suffix . '@example.test',
            'status' => 'active',
        ]);

        $affiliate = Affiliate::create([
            'customer_id' => $customer->id,
            'affiliate_code' => 'CODE-' . strtoupper($suffix) . '-001',
            'status' => 'active',
            'commission_rate' => 10,
            'commission_type' => 'percentage',
            'total_earned' => 0,
            'total_paid' => 0,
            'balance' => 0,
        ]);

        $commission = AffiliateCommission::create([
            'affiliate_id' => $affiliate->id,
            'description' => 'Test commission ' . strtoupper($suffix),
            'amount' => 25,
            'commission_rate' => 10,
            'status' => 'pending',
        ]);

        return [$affiliate, $commission];
    }
}
