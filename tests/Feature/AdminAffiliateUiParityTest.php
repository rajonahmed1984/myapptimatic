<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Affiliate;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminAffiliateUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_affiliate_pages_render_inertia_for_authorized_admin(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $affiliate = $this->createAffiliate('a');

        $this->actingAs($admin)
            ->get(route('admin.affiliates.index'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Affiliates\\/Index', false);

        $this->actingAs($admin)
            ->get(route('admin.affiliates.create'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Affiliates\\/Form', false);

        $this->actingAs($admin)
            ->get(route('admin.affiliates.show', $affiliate))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Affiliates\\/Show', false);

        $this->actingAs($admin)
            ->get(route('admin.affiliates.edit', $affiliate))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Affiliates\\/Form', false);
    }

    #[Test]
    public function admin_affiliate_pages_keep_permission_guard_for_client_role(): void
    {
        $client = User::factory()->create(['role' => Role::CLIENT]);
        $affiliate = $this->createAffiliate('b');

        $this->actingAs($client)
            ->get(route('admin.affiliates.index'))
            ->assertForbidden();
        $this->actingAs($client)
            ->get(route('admin.affiliates.create'))
            ->assertForbidden();
        $this->actingAs($client)
            ->get(route('admin.affiliates.show', $affiliate))
            ->assertForbidden();
        $this->actingAs($client)
            ->get(route('admin.affiliates.edit', $affiliate))
            ->assertForbidden();
    }

    #[Test]
    public function admin_affiliate_store_update_destroy_keep_redirect_and_flash_contracts(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $customer = $this->createCustomer('store');
        $storeResponse = $this->actingAs($admin)
            ->post(route('admin.affiliates.store'), [
                'customer_id' => $customer->id,
                'commission_rate' => 15.5,
                'commission_type' => 'percentage',
                'fixed_commission_amount' => null,
                'status' => 'active',
                'payment_details' => 'Bank transfer',
                'notes' => 'Initial setup',
            ]);

        $storeResponse
            ->assertRedirect(route('admin.affiliates.index'))
            ->assertSessionHas('status', 'Affiliate created successfully.');

        $affiliate = Affiliate::query()
            ->where('customer_id', $customer->id)
            ->first();

        $this->assertNotNull($affiliate);
        $this->assertNotEmpty((string) $affiliate?->affiliate_code);
        $this->assertSame('active', $affiliate?->status);
        $this->assertNotNull($affiliate?->approved_at);

        $replacementCustomer = $this->createCustomer('update');
        $updateResponse = $this->actingAs($admin)
            ->put(route('admin.affiliates.update', $affiliate), [
                'customer_id' => $replacementCustomer->id,
                'commission_rate' => 5.25,
                'commission_type' => 'fixed',
                'fixed_commission_amount' => 20,
                'status' => 'inactive',
                'payment_details' => 'Updated payment details',
                'notes' => 'Updated note',
            ]);

        $updateResponse
            ->assertRedirect(route('admin.affiliates.show', $affiliate))
            ->assertSessionHas('status', 'Affiliate updated successfully.');

        $affiliate->refresh();
        $this->assertSame($replacementCustomer->id, $affiliate->customer_id);
        $this->assertSame('inactive', $affiliate->status);
        $this->assertSame('fixed', $affiliate->commission_type);
        $this->assertEquals(20.0, (float) $affiliate->fixed_commission_amount);

        $this->actingAs($admin)
            ->delete(route('admin.affiliates.destroy', $affiliate))
            ->assertRedirect(route('admin.affiliates.index'))
            ->assertSessionHas('status', 'Affiliate deleted successfully.');

        $this->assertDatabaseMissing('affiliates', ['id' => $affiliate->id]);
    }

    #[Test]
    public function admin_affiliate_store_and_update_keep_validation_error_keys(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $this->actingAs($admin)
            ->from(route('admin.affiliates.create'))
            ->post(route('admin.affiliates.store'), [])
            ->assertRedirect(route('admin.affiliates.create'))
            ->assertSessionHasErrors([
                'customer_id',
                'commission_rate',
                'commission_type',
                'status',
            ]);

        $affiliate = $this->createAffiliate('validation');
        $this->actingAs($admin)
            ->from(route('admin.affiliates.edit', $affiliate))
            ->put(route('admin.affiliates.update', $affiliate), [
                'customer_id' => $affiliate->customer_id,
                'commission_rate' => 200,
                'commission_type' => 'invalid',
                'status' => 'bad',
            ])
            ->assertRedirect(route('admin.affiliates.edit', $affiliate))
            ->assertSessionHasErrors([
                'commission_rate',
                'commission_type',
                'status',
            ]);
    }

    private function createAffiliate(string $suffix): Affiliate
    {
        $customer = $this->createCustomer('affiliate-' . $suffix);

        return Affiliate::create([
            'customer_id' => $customer->id,
            'affiliate_code' => 'AFF-' . strtoupper($suffix) . '-001',
            'status' => 'active',
            'commission_rate' => 10,
            'commission_type' => 'percentage',
            'fixed_commission_amount' => null,
            'total_earned' => 100,
            'total_paid' => 40,
            'balance' => 60,
            'total_referrals' => 8,
            'total_conversions' => 3,
        ]);
    }

    private function createCustomer(string $suffix): Customer
    {
        return Customer::create([
            'name' => 'Affiliate Customer ' . strtoupper($suffix),
            'email' => 'affiliate-customer-' . $suffix . '@example.test',
            'status' => 'active',
        ]);
    }
}
