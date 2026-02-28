<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Product;
use App\Models\SalesRepresentative;
use App\Models\Setting;
use App\Models\Subscription;
use App\Services\BillingService;
use App\Services\CommissionService;
use App\Services\InvoiceTaxService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubscriptionAmountAndCommissionOverrideTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function billing_service_uses_subscription_amount_when_present(): void
    {
        Setting::setValue('invoice_due_days', 7);
        Setting::setValue('currency', 'USD');
        Carbon::setTestNow(Carbon::parse('2026-02-01'));

        $customer = Customer::create([
            'name' => 'Override Customer',
            'status' => 'active',
        ]);

        $product = Product::create([
            'name' => 'Override Product',
            'slug' => 'override-product',
            'status' => 'active',
        ]);

        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Override Plan',
            'interval' => 'monthly',
            'price' => 99.00,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'subscription_amount' => 149.00,
            'start_date' => '2026-02-01',
            'current_period_start' => '2026-02-01',
            'current_period_end' => '2026-02-28',
            'next_invoice_at' => '2026-02-01',
            'auto_renew' => true,
            'cancel_at_period_end' => false,
        ]);

        $billingService = new BillingService(app(InvoiceTaxService::class));
        $invoice = $billingService->generateInvoiceForSubscription($subscription, Carbon::parse('2026-02-01'));

        $this->assertNotNull($invoice);
        $this->assertSame(149.00, (float) $invoice->subtotal);
        $this->assertSame(149.00, (float) $invoice->items()->first()->unit_price);
    }

    #[Test]
    public function commission_service_uses_subscription_commission_override_when_present(): void
    {
        $customer = Customer::create([
            'name' => 'Commission Customer',
            'status' => 'active',
        ]);

        $product = Product::create([
            'name' => 'Commission Product',
            'slug' => 'commission-product',
            'status' => 'active',
        ]);

        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Commission Plan',
            'interval' => 'monthly',
            'price' => 200.00,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $salesRep = SalesRepresentative::create([
            'name' => 'Commission Rep',
            'status' => 'active',
        ]);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'sales_rep_id' => $salesRep->id,
            'sales_rep_commission_amount' => 37.50,
            'status' => 'active',
            'start_date' => '2026-02-01',
            'current_period_start' => '2026-02-01',
            'current_period_end' => '2026-02-28',
            'next_invoice_at' => '2026-02-01',
            'auto_renew' => true,
            'cancel_at_period_end' => false,
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'number' => 'T-1001',
            'status' => 'paid',
            'issue_date' => '2026-02-01',
            'due_date' => '2026-02-08',
            'subtotal' => 200.00,
            'late_fee' => 0.00,
            'total' => 200.00,
            'currency' => 'USD',
        ]);

        $earning = app(CommissionService::class)->createOrUpdateEarningOnInvoicePaid($invoice->fresh('subscription'));

        $this->assertNotNull($earning);
        $this->assertSame(37.50, (float) $earning->commission_amount);
    }
}
