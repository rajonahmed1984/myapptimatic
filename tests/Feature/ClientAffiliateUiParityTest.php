<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\AffiliateReferral;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientAffiliateUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function non_enrolled_client_routes_render_not_enrolled_and_apply_pages(): void
    {
        $customer = Customer::create(['name' => 'Affiliate Customer']);
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($client)
            ->get(route('client.affiliates.index'))
            ->assertOk()
            ->assertSee('Client\\/Affiliates\\/NotEnrolled', false);

        $this->actingAs($client)
            ->get(route('client.affiliates.apply'))
            ->assertOk()
            ->assertSee('Client\\/Affiliates\\/Apply', false);
    }

    #[Test]
    public function enrolled_client_affiliate_routes_render_inertia_pages(): void
    {
        $customer = Customer::create(['name' => 'Affiliate Customer']);
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $affiliate = Affiliate::create([
            'customer_id' => $customer->id,
            'affiliate_code' => 'AFFCODE1234',
            'status' => 'active',
            'commission_rate' => 10,
            'commission_type' => 'percentage',
            'total_earned' => 100,
            'total_paid' => 40,
            'balance' => 60,
        ]);

        AffiliateReferral::create([
            'affiliate_id' => $affiliate->id,
            'status' => 'pending',
        ]);

        AffiliateCommission::create([
            'affiliate_id' => $affiliate->id,
            'description' => 'Commission',
            'amount' => 10,
            'commission_rate' => 10,
            'status' => 'pending',
        ]);

        AffiliatePayout::create([
            'affiliate_id' => $affiliate->id,
            'payout_number' => 'PO-2026-000001',
            'amount' => 50,
            'status' => 'pending',
        ]);

        $this->actingAs($client)
            ->get(route('client.affiliates.index'))
            ->assertOk()
            ->assertSee('Client\\/Affiliates\\/Dashboard', false);

        $this->actingAs($client)
            ->get(route('client.affiliates.referrals'))
            ->assertOk()
            ->assertSee('Client\\/Affiliates\\/Referrals', false);

        $this->actingAs($client)
            ->get(route('client.affiliates.commissions'))
            ->assertOk()
            ->assertSee('Client\\/Affiliates\\/Commissions', false);

        $this->actingAs($client)
            ->get(route('client.affiliates.payouts'))
            ->assertOk()
            ->assertSee('Client\\/Affiliates\\/Payouts', false);

        $this->actingAs($client)
            ->get(route('client.affiliates.settings'))
            ->assertOk()
            ->assertSee('Client\\/Affiliates\\/Settings', false);
    }
}
