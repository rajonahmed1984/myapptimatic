<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RunBillingCycleAutoRenewTest extends TestCase
{
    use RefreshDatabase;

    private function makeSubscription(Customer $customer, Plan $plan, bool $autoRenew, bool $cancelAtPeriodEnd): Subscription
    {
        return Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => '2026-04-01',
            'current_period_start' => '2026-04-01',
            'current_period_end' => '2026-04-30',
            'next_invoice_at' => '2026-04-30',
            'auto_renew' => $autoRenew,
            'cancel_at_period_end' => $cancelAtPeriodEnd,
        ]);
    }

    #[Test]
    public function auto_renew_false_stops_billing_at_period_end_like_cancel_at_period_end(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-04-30 08:00:00'));

        Setting::setValue('currency', 'USD');
        Setting::setValue('invoice_due_days', 7);
        Setting::setValue('enable_suspension', 0);
        Setting::setValue('enable_termination', 0);
        Setting::setValue('enable_unsuspension', 0);

        $customer = Customer::create(['name' => 'Auto Renew Off Customer', 'status' => 'active']);
        $product = Product::create(['name' => 'Auto Renew Product', 'slug' => 'auto-renew-product', 'status' => 'active']);
        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Monthly Plan',
            'slug' => 'auto-renew-monthly-plan',
            'interval' => 'monthly',
            'price' => 50,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $subscription = $this->makeSubscription($customer, $plan, autoRenew: false, cancelAtPeriodEnd: false);

        $this->artisan('billing:run')->assertExitCode(0);

        $this->assertDatabaseMissing('invoices', [
            'subscription_id' => $subscription->id,
        ]);

        $subscription->refresh();
        $this->assertSame('cancelled', $subscription->status);
        $this->assertFalse((bool) $subscription->auto_renew);
    }

    #[Test]
    public function auto_renew_true_still_bills_normally(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-04-30 08:00:00'));

        Setting::setValue('currency', 'USD');
        Setting::setValue('invoice_due_days', 7);
        Setting::setValue('enable_suspension', 0);
        Setting::setValue('enable_termination', 0);
        Setting::setValue('enable_unsuspension', 0);

        $customer = Customer::create(['name' => 'Auto Renew On Customer', 'status' => 'active']);
        $product = Product::create(['name' => 'Auto Renew Product 2', 'slug' => 'auto-renew-product-2', 'status' => 'active']);
        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Monthly Plan 2',
            'slug' => 'auto-renew-monthly-plan-2',
            'interval' => 'monthly',
            'price' => 50,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $subscription = $this->makeSubscription($customer, $plan, autoRenew: true, cancelAtPeriodEnd: false);

        $this->artisan('billing:run')->assertExitCode(0);

        $this->assertDatabaseHas('invoices', [
            'subscription_id' => $subscription->id,
            'customer_id' => $customer->id,
        ]);

        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
    }
}
