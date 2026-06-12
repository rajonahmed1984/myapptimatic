<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FirstOfMonthBillingAndRemindersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function next_invoice_at_remains_1st_of_month_on_creation(): void
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'status' => 'active',
        ]);

        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'status' => 'active',
        ]);

        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Monthly Plan',
            'slug' => 'monthly-plan',
            'interval' => 'monthly',
            'price' => 100,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        // Creating a subscription with start date July 1st
        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => '2026-07-01',
            'current_period_start' => '2026-07-01',
            'current_period_end' => '2026-07-31',
            'next_invoice_at' => '2026-07-01',
            'auto_renew' => true,
            'cancel_at_period_end' => false,
        ]);

        // next_invoice_at should remain exactly July 1st in the database
        $this->assertEquals('2026-07-01', $subscription->fresh()->next_invoice_at->toDateString());
    }

    #[Test]
    public function billing_run_generates_invoice_10_days_before_and_sets_due_date_to_1st(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-06-21 09:00:00'));

        Setting::setValue('currency', 'USD');
        Setting::setValue('invoice_due_days', 7);
        Setting::setValue('enable_suspension', 0);
        Setting::setValue('enable_termination', 0);
        Setting::setValue('enable_unsuspension', 0);

        $customer = Customer::create(['name' => 'Test Customer', 'status' => 'active']);
        $product = Product::create(['name' => 'Test Product', 'slug' => 'test-product', 'status' => 'active']);
        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Monthly Plan',
            'slug' => 'monthly-plan',
            'interval' => 'monthly',
            'price' => 100,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => '2026-07-01',
            'current_period_start' => '2026-07-01',
            'current_period_end' => '2026-07-31',
            'next_invoice_at' => '2026-07-01',
            'auto_renew' => true,
            'cancel_at_period_end' => false,
        ]);

        $this->artisan('billing:run')->assertExitCode(0);

        $invoice = Invoice::where('subscription_id', $subscription->id)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals('2026-06-21', $invoice->issue_date->toDateString());
        $this->assertEquals('2026-07-01', $invoice->due_date->toDateString());

        // Subscription's next period starts August 1st.
        // next_invoice_at should be exactly August 1st in the database
        $this->assertEquals('2026-08-01', $subscription->fresh()->next_invoice_at->toDateString());
        $this->assertEquals('2026-08-01', $subscription->fresh()->current_period_start->toDateString());
        $this->assertEquals('2026-08-31', $subscription->fresh()->current_period_end->toDateString());
    }

    #[Test]
    public function due_reminders_are_sent_on_specified_intervals_and_exclude_standard_reminders(): void
    {
        Queue::fake();
        Setting::setValue('payment_reminder_emails', 1);
        Setting::setValue('invoice_unpaid_reminder_days', 10); // Standard reminder day matches our created day target

        $customer = Customer::create(['name' => 'Test Customer', 'status' => 'active']);
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-100',
            'status' => 'unpaid',
            'issue_date' => '2026-06-21',
            'due_date' => '2026-07-01', // Due on the 1st
            'subtotal' => 100,
            'tax_rate_percent' => 0,
            'tax_mode' => 'exclusive',
            'tax_amount' => 0,
            'late_fee' => 0,
            'total' => 100,
            'currency' => 'USD',
        ]);

        // 1. Created day (10 days before) reminder
        Carbon::setTestNow(Carbon::parse('2026-06-21 09:00:00'));
        $this->artisan('billing:run')->assertExitCode(0);

        $invoice = $invoice->fresh();
        $this->assertNotNull($invoice->reminder_created_day_sent_at);
        $this->assertNull($invoice->reminder_sent_at); // Standard reminder is bypassed for 1st-of-month

        // 2. 5 days before reminder
        Carbon::setTestNow(Carbon::parse('2026-06-26 09:00:00'));
        $this->artisan('billing:run')->assertExitCode(0);

        $invoice = $invoice->fresh();
        $this->assertNotNull($invoice->reminder_5d_before_sent_at);

        // 3. 3 days before reminder
        Carbon::setTestNow(Carbon::parse('2026-06-28 09:00:00'));
        $this->artisan('billing:run')->assertExitCode(0);

        $invoice = $invoice->fresh();
        $this->assertNotNull($invoice->reminder_3d_before_sent_at);

        // 4. 1 day before reminder
        Carbon::setTestNow(Carbon::parse('2026-06-30 09:00:00'));
        $this->artisan('billing:run')->assertExitCode(0);

        $invoice = $invoice->fresh();
        $this->assertNotNull($invoice->reminder_1d_before_sent_at);
    }
}
