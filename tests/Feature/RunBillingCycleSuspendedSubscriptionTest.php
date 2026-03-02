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

class RunBillingCycleSuspendedSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function billing_run_generates_invoice_for_suspended_subscription(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-04-01 08:00:00'));

        Setting::setValue('currency', 'USD');
        Setting::setValue('invoice_due_days', 7);
        Setting::setValue('enable_suspension', 0);
        Setting::setValue('enable_termination', 0);
        Setting::setValue('enable_unsuspension', 0);

        $customer = Customer::create([
            'name' => 'Suspended Billing Customer',
            'status' => 'active',
        ]);

        $product = Product::create([
            'name' => 'Suspended Plan Product',
            'slug' => 'suspended-plan-product',
            'status' => 'active',
        ]);

        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Monthly Plan',
            'slug' => 'monthly-plan',
            'interval' => 'monthly',
            'price' => 50,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'suspended',
            'start_date' => '2026-04-01',
            'current_period_start' => '2026-04-01',
            'current_period_end' => '2026-04-30',
            'next_invoice_at' => '2026-04-01',
            'auto_renew' => true,
            'cancel_at_period_end' => false,
        ]);

        $this->artisan('billing:run')->assertExitCode(0);

        $this->assertDatabaseHas('invoices', [
            'subscription_id' => $subscription->id,
            'customer_id' => $customer->id,
            'status' => 'unpaid',
            'issue_date' => '2026-04-01 00:00:00',
        ]);

        $this->assertSame(
            '2026-05-01',
            $subscription->fresh()->next_invoice_at?->toDateString()
        );
    }
}
