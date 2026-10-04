<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\MyBuildingProvision;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\InvoicePaymentCompletionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubscriptionLicenseAdminGuardsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Carbon::setTestNow('2026-10-04 09:00:00');
        Setting::setValue('currency', 'USD');

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->customer = Customer::create(['name' => 'Guard Customer', 'status' => 'active']);
        $this->plan = $this->makePlan('Fixed', 2000);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function the_edit_form_cannot_change_the_client(): void
    {
        $subscription = $this->subscription();
        $other = Customer::create(['name' => 'Someone Else', 'status' => 'active']);

        $this->actingAs($this->admin)
            ->put(route('admin.subscriptions.update', $subscription), $this->updatePayload($subscription, ['customer_id' => $other->id]))
            ->assertSessionHasErrors('customer_id');

        $this->assertSame($this->customer->id, $subscription->fresh()->customer_id);
    }

    #[Test]
    public function suspending_from_the_edit_form_suspends_licenses_and_reactivating_restores_them(): void
    {
        $subscription = $this->subscription();
        $license = $this->license($subscription);
        $held = $this->license($subscription);

        // An admin holds one license on its own first.
        $this->actingAs($this->admin)->post(route('admin.licenses.suspend', $held));

        $this->actingAs($this->admin)
            ->put(route('admin.subscriptions.update', $subscription), $this->updatePayload($subscription, ['status' => 'suspended']))
            ->assertRedirect();

        $this->assertSame('suspended', $license->fresh()->status);

        $this->actingAs($this->admin)
            ->put(route('admin.subscriptions.update', $subscription), $this->updatePayload($subscription, ['status' => 'active']))
            ->assertRedirect();

        $this->assertSame('active', $license->fresh()->status);
        $this->assertSame('suspended', $held->fresh()->status, 'An admin hold must survive re-activation.');
    }

    #[Test]
    public function cancelling_from_the_edit_form_revokes_licenses(): void
    {
        $subscription = $this->subscription();
        $license = $this->license($subscription);

        $this->actingAs($this->admin)
            ->put(route('admin.subscriptions.update', $subscription), $this->updatePayload($subscription, ['status' => 'cancelled', 'auto_renew' => '1']))
            ->assertRedirect();

        $subscription->refresh();
        $this->assertSame('revoked', $license->fresh()->status);
        $this->assertNotNull($subscription->cancelled_at);
        $this->assertFalse($subscription->auto_renew);
    }

    #[Test]
    public function paying_does_not_lift_an_admin_hold(): void
    {
        $subscription = $this->subscription();
        $held = $this->license($subscription);
        $autoSuspended = $this->license($subscription, ['status' => 'suspended']);

        $this->actingAs($this->admin)->post(route('admin.licenses.suspend', $held));

        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'subscription_id' => $subscription->id,
            'number' => 'INV-GUARD-1',
            'status' => 'unpaid',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-01',
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'subtotal' => 2000,
            'late_fee' => 0,
            'total' => 2000,
            'currency' => 'USD',
        ]);

        app(InvoicePaymentCompletionService::class)->complete($invoice, ['notify' => false]);

        $this->assertSame('suspended', $held->fresh()->status);
        $this->assertSame('active', $autoSuspended->fresh()->status);
    }

    #[Test]
    public function changing_to_a_per_flat_plan_prices_by_the_buildings_flats(): void
    {
        $subscription = $this->subscription(['sales_rep_commission_amount' => 200]);
        $license = $this->license($subscription);
        MyBuildingProvision::create([
            'license_id' => $license->id,
            'customer_id' => $this->customer->id,
            'building_name' => 'Tower',
            'total_floors' => 5,
            'flats_per_floor' => 4,
            'contracted_flats' => 20,
            'owner_name' => 'Owner',
            'owner_email' => '',
            'owner_phone' => '',
            'install_url' => '',
        ]);
        $perFlat = $this->makePlan('Per flat', 50, ['pricing_model' => 'per_flat']);

        $this->actingAs($this->admin)
            ->put(route('admin.subscriptions.change-plan', $subscription), ['plan_id' => $perFlat->id])
            ->assertRedirect();

        $subscription->refresh();
        $this->assertSame('1000.00', (string) $subscription->subscription_amount);
        // Commission stays at 10%.
        $this->assertSame('100.00', (string) $subscription->sales_rep_commission_amount);
    }

    #[Test]
    public function a_per_flat_plan_change_without_a_flat_count_needs_an_amount(): void
    {
        $subscription = $this->subscription();
        $perFlat = $this->makePlan('Per flat', 50, ['pricing_model' => 'per_flat']);

        $this->actingAs($this->admin)
            ->put(route('admin.subscriptions.change-plan', $subscription), ['plan_id' => $perFlat->id])
            ->assertSessionHasErrors('subscription_amount');

        $this->assertSame($this->plan->id, $subscription->fresh()->plan_id);
    }

    #[Test]
    public function a_cancelled_subscription_cannot_change_plan(): void
    {
        $subscription = $this->subscription(['status' => 'cancelled']);
        $other = $this->makePlan('Other', 3000);

        $this->actingAs($this->admin)
            ->put(route('admin.subscriptions.change-plan', $subscription), ['plan_id' => $other->id])
            ->assertSessionHas('error');

        $this->assertSame($this->plan->id, $subscription->fresh()->plan_id);
    }

    #[Test]
    public function a_subscription_with_live_licenses_cannot_be_deleted(): void
    {
        $subscription = $this->subscription();
        $license = $this->license($subscription);

        $this->actingAs($this->admin)
            ->delete(route('admin.subscriptions.destroy', $subscription))
            ->assertSessionHas('error');

        $this->assertNotNull($subscription->fresh());
        $this->assertNotNull($license->fresh());
    }

    #[Test]
    public function an_empty_subscription_can_still_be_deleted(): void
    {
        $subscription = $this->subscription();

        $this->actingAs($this->admin)
            ->delete(route('admin.subscriptions.destroy', $subscription))
            ->assertRedirect(route('admin.subscriptions.index'));

        $this->assertNull($subscription->fresh());
    }

    #[Test]
    public function the_license_form_cannot_move_a_license_to_another_subscription(): void
    {
        $subscription = $this->subscription();
        $target = $this->subscription();
        $license = $this->license($subscription);

        $this->actingAs($this->admin)->put(route('admin.licenses.update', $license), [
            'subscription_id' => $target->id,
            'product_id' => $license->product_id,
            'license_key' => $license->license_key,
            'status' => 'active',
            'starts_at' => '2026-10-01',
            'expires_at' => '2026-10-31',
            'allowed_domains' => 'example.com',
        ])->assertSessionHasErrors('subscription_id');

        $this->assertSame($subscription->id, $license->fresh()->subscription_id);
    }

    #[Test]
    public function an_admin_sync_keeps_the_installations_ip(): void
    {
        $license = $this->license($this->subscription(), ['last_check_ip' => '203.0.113.7']);

        $this->actingAs($this->admin)
            ->postJson(route('admin.licenses.sync', $license))
            ->assertOk();

        $this->assertSame('203.0.113.7', $license->fresh()->last_check_ip);
    }

    private function makePlan(string $name, float $price, array $extra = []): Plan
    {
        $product = Product::create(['name' => $name.' Product', 'slug' => 'guard-'.uniqid(), 'status' => 'active']);

        return Plan::create(array_merge([
            'product_id' => $product->id,
            'name' => $name,
            'slug' => 'guard-plan-'.uniqid(),
            'interval' => 'monthly',
            'price' => $price,
            'currency' => 'USD',
            'is_active' => true,
        ], $extra));
    }

    private function subscription(array $extra = []): Subscription
    {
        return Subscription::create(array_merge([
            'customer_id' => $this->customer->id,
            'plan_id' => $this->plan->id,
            'subscription_amount' => 2000,
            'status' => 'active',
            'start_date' => '2026-10-01',
            'current_period_start' => '2026-10-01',
            'current_period_end' => '2026-10-31',
            'next_invoice_at' => '2026-11-01',
            'auto_renew' => true,
            'cancel_at_period_end' => false,
        ], $extra));
    }

    private function license(Subscription $subscription, array $extra = []): License
    {
        return License::create(array_merge([
            'subscription_id' => $subscription->id,
            'product_id' => $subscription->plan->product_id,
            'license_key' => strtoupper(uniqid('GUARD')),
            'status' => 'active',
            'starts_at' => '2026-10-01',
            'expires_at' => '2026-10-31',
            'max_domains' => 1,
        ], $extra));
    }

    private function updatePayload(Subscription $subscription, array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $subscription->customer_id,
            'plan_id' => $subscription->plan_id,
            'status' => $subscription->status,
            'start_date' => '01-10-2026',
            'current_period_start' => '01-10-2026',
            'current_period_end' => '31-10-2026',
            'next_invoice_at' => '01-11-2026',
            'subscription_amount' => 2000,
            'auto_renew' => '1',
        ], $overrides);
    }
}
