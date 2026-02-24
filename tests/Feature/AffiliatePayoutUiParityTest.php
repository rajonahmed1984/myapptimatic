<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AffiliatePayoutUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function affiliate_payout_index_create_and_show_render_inertia(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        [$affiliate, $commission] = $this->seedAffiliateWithApprovedCommission('a');

        config()->set('features.admin_affiliate_payouts_ui', false);
        $index = $this->actingAs($admin)->get(route('admin.affiliates.payouts.index'));
        $index->assertOk();
        $index->assertSee('data-page=');
        $index->assertSee('Admin\\/Affiliates\\/Payouts\\/Index', false);

        config()->set('features.admin_affiliate_payouts_ui', true);
        $create = $this->actingAs($admin)->get(route('admin.affiliates.payouts.create', ['affiliate_id' => $affiliate->id]));
        $create->assertOk();
        $create->assertSee('data-page=');
        $create->assertSee('Admin\\/Affiliates\\/Payouts\\/Create', false);

        $payout = AffiliatePayout::create([
            'affiliate_id' => $affiliate->id,
            'payout_number' => AffiliatePayout::generatePayoutNumber(),
            'amount' => 25,
            'status' => 'pending',
        ]);
        $commission->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payout_id' => $payout->id,
        ]);

        $show = $this->actingAs($admin)->get(route('admin.affiliates.payouts.show', $payout));
        $show->assertOk();
        $show->assertSee('data-page=');
        $show->assertSee('Admin\\/Affiliates\\/Payouts\\/Show', false);
    }

    #[Test]
    public function affiliate_payout_routes_keep_permission_guard_for_client_role(): void
    {
        $client = User::factory()->create(['role' => Role::CLIENT]);
        [$affiliate] = $this->seedAffiliateWithApprovedCommission('b');
        $payout = AffiliatePayout::create([
            'affiliate_id' => $affiliate->id,
            'payout_number' => AffiliatePayout::generatePayoutNumber(),
            'amount' => 20,
            'status' => 'pending',
        ]);

        $this->actingAs($client)
            ->get(route('admin.affiliates.payouts.index'))
            ->assertForbidden();
        $this->actingAs($client)
            ->get(route('admin.affiliates.payouts.create'))
            ->assertForbidden();
        $this->actingAs($client)
            ->get(route('admin.affiliates.payouts.show', $payout))
            ->assertForbidden();
    }

    #[Test]
    public function affiliate_payout_complete_and_destroy_actions_keep_contract_when_flag_toggles(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        [$affiliateA, $commissionA] = $this->seedAffiliateWithApprovedCommission('c');

        $payoutA = AffiliatePayout::create([
            'affiliate_id' => $affiliateA->id,
            'payout_number' => AffiliatePayout::generatePayoutNumber(),
            'amount' => 25,
            'status' => 'pending',
        ]);
        $commissionA->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payout_id' => $payoutA->id,
        ]);

        config()->set('features.admin_affiliate_payouts_ui', false);
        $this->actingAs($admin)
            ->from(route('admin.affiliates.payouts.show', $payoutA))
            ->post(route('admin.affiliates.payouts.complete', $payoutA))
            ->assertRedirect(route('admin.affiliates.payouts.show', $payoutA))
            ->assertSessionHas('status', 'Payout marked as completed.');
        $payoutA->refresh();
        $this->assertSame('completed', $payoutA->status);

        [$affiliateB, $commissionB] = $this->seedAffiliateWithApprovedCommission('d');
        $affiliateB->update(['total_earned' => 40, 'total_paid' => 40, 'balance' => 0]);
        $payoutB = AffiliatePayout::create([
            'affiliate_id' => $affiliateB->id,
            'payout_number' => AffiliatePayout::generatePayoutNumber(),
            'amount' => 40,
            'status' => 'pending',
        ]);
        $commissionB->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payout_id' => $payoutB->id,
        ]);

        config()->set('features.admin_affiliate_payouts_ui', true);
        $this->actingAs($admin)
            ->delete(route('admin.affiliates.payouts.destroy', $payoutB))
            ->assertRedirect(route('admin.affiliates.payouts.index'))
            ->assertSessionHas('status', 'Payout deleted successfully.');

        $this->assertDatabaseMissing('affiliate_payouts', ['id' => $payoutB->id]);
        $commissionB->refresh();
        $this->assertSame('approved', $commissionB->status);
        $this->assertNull($commissionB->paid_at);
        $this->assertNull($commissionB->payout_id);
        $affiliateB->refresh();
        $this->assertEquals(0.0, (float) $affiliateB->total_paid);
        $this->assertEquals(40.0, (float) $affiliateB->balance);
    }

    /**
     * @return array{Affiliate, AffiliateCommission}
     */
    private function seedAffiliateWithApprovedCommission(string $suffix): array
    {
        $customer = Customer::create([
            'name' => 'Payout Affiliate ' . strtoupper($suffix),
            'email' => 'payout-affiliate-' . $suffix . '@example.test',
            'status' => 'active',
        ]);

        $affiliate = Affiliate::create([
            'customer_id' => $customer->id,
            'affiliate_code' => 'PAYOUT-' . strtoupper($suffix) . '-001',
            'status' => 'active',
            'commission_rate' => 10,
            'commission_type' => 'percentage',
            'total_earned' => 0,
            'total_paid' => 0,
            'balance' => 10,
        ]);

        $commission = AffiliateCommission::create([
            'affiliate_id' => $affiliate->id,
            'description' => 'Approved payout commission ' . strtoupper($suffix),
            'amount' => 25,
            'commission_rate' => 10,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        return [$affiliate, $commission];
    }
}
