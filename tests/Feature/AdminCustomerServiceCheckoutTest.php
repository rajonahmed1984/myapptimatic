<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Product;
use App\Models\SalesRepresentative;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminCustomerServiceCheckoutTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_add_product_service_for_customer_with_prorated_checkout_and_sales_rep_commission(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $customer = Customer::create([
            'name' => 'Checkout Customer',
            'status' => 'active',
        ]);

        $product = Product::create([
            'name' => 'Checkout Product',
            'slug' => 'checkout-product',
            'status' => 'active',
        ]);

        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Monthly Plan',
            'slug' => 'monthly-plan',
            'interval' => 'monthly',
            'price' => 100.00,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $salesRep = SalesRepresentative::create([
            'name' => 'Rep One',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.customers.services.store', $customer), [
            'plan_id' => $plan->id,
            'start_date' => '2026-02-10',
            'sales_rep_id' => $salesRep->id,
            'sales_rep_commission_amount' => 25.50,
        ]);

        $response->assertRedirect(route('admin.customers.show', ['customer' => $customer, 'tab' => 'services']));

        $subscription = Subscription::query()->first();
        $this->assertNotNull($subscription);
        $this->assertSame($customer->id, $subscription->customer_id);
        $this->assertSame($plan->id, $subscription->plan_id);
        $this->assertSame($salesRep->id, $subscription->sales_rep_id);
        $this->assertSame(25.50, (float) $subscription->sales_rep_commission_amount);
        $this->assertSame('pending', $subscription->status);
        $this->assertSame('2026-02-28', $subscription->current_period_end?->toDateString());

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'sales_rep_id' => $salesRep->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('invoices', [
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'subtotal' => 67.86,
            'total' => 67.86,
        ]);
    }
}
