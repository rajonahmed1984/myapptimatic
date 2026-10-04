<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\BillingService;
use App\Services\InvoicePaymentCompletionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubscriptionBillingRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Setting::setValue('currency', 'USD');
        Setting::setValue('invoice_due_days', 7);
        Setting::setValue('invoice_generation_days', 15);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function admin_can_create_an_active_per_flat_subscription(): void
    {
        Carbon::setTestNow('2026-10-04 09:00:00');
        [$customer, $product] = $this->basics();
        $plan = $this->plan($product, 'monthly', 50, ['pricing_model' => 'per_flat']);

        $response = $this->actingAs($this->admin())->post(route('admin.subscriptions.store'), [
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'start_date' => '2026-10-04',
            'status' => 'active',
            'total_floors' => 5,
            'contracted_flats' => 20,
        ]);

        $subscription = Subscription::firstOrFail();
        $response->assertRedirect(route('admin.subscriptions.edit', $subscription));
        $this->assertSame(1, $subscription->licenses()->count());
    }

    #[Test]
    public function a_subscription_created_without_an_auto_renew_field_renews(): void
    {
        Carbon::setTestNow('2026-10-04 09:00:00');
        [$customer, $product] = $this->basics();
        $plan = $this->plan($product, 'monthly', 100);

        $this->actingAs($this->admin())->post(route('admin.subscriptions.store'), [
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'start_date' => '2026-10-04',
            'status' => 'active',
        ])->assertRedirect();

        $this->assertTrue(Subscription::firstOrFail()->auto_renew);
    }

    #[Test]
    public function the_create_form_ticks_auto_renew_by_default(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.subscriptions.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('form.fields.auto_renew', true));
    }

    #[Test]
    public function updating_without_auto_renew_keeps_the_current_value(): void
    {
        [$customer, $product] = $this->basics();
        $plan = $this->plan($product, 'monthly', 100);
        $subscription = $this->subscription($customer, $plan, '2026-10-01', '2026-10-31', '2026-11-01');

        $this->actingAs($this->admin())->put(route('admin.subscriptions.update', $subscription), [
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => '01-10-2026',
            'current_period_start' => '01-10-2026',
            'current_period_end' => '31-10-2026',
            'next_invoice_at' => '01-11-2026',
        ])->assertRedirect();

        $this->assertTrue($subscription->fresh()->auto_renew);
    }

    #[Test]
    public function a_yearly_renewal_is_invoiced_ahead_of_the_next_term(): void
    {
        Carbon::setTestNow('2026-10-04 09:00:00');
        [$customer, $product] = $this->basics();
        $plan = $this->plan($product, 'yearly', 1200);
        $subscription = $this->subscription($customer, $plan, '2026-10-04', '2027-10-04', '2026-10-04');

        $invoice = app(BillingService::class)->generateInvoiceForSubscription($subscription, Carbon::today());

        $this->assertNotNull($invoice);
        $subscription->refresh();
        $this->assertSame('2027-10-04', $subscription->current_period_start->toDateString());
        // 15 days ahead of the next term, not 15 days before it ends.
        $this->assertSame('2027-09-19', $subscription->next_invoice_at->toDateString());
    }

    #[Test]
    public function paying_an_invoice_extends_the_license_only_through_the_billed_period(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');
        [$customer, $product] = $this->basics();
        $plan = $this->plan($product, 'monthly', 100);
        $subscription = $this->subscription($customer, $plan, '2026-10-10', '2026-10-31', '2026-10-10');
        $license = $this->license($subscription, '2026-10-31');

        $invoice = app(BillingService::class)->generateInvoiceForSubscription($subscription, Carbon::today());

        $this->assertSame('2026-10-10', $invoice->period_start->toDateString());
        $this->assertSame('2026-10-31', $invoice->period_end->toDateString());
        // The window has already moved on to November, which nobody has paid.
        $this->assertSame('2026-11-30', $subscription->fresh()->current_period_end->toDateString());

        app(InvoicePaymentCompletionService::class)->complete($invoice, ['notify' => false]);

        // October paid, plus the 5-day grace that matches invoice grace.
        $this->assertSame('2026-11-05', $license->fresh()->expires_at->toDateString());
    }

    #[Test]
    public function the_expiry_grace_follows_the_setting_when_one_is_saved(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');
        Setting::setValue('license_expiry_grace_days', 0);
        [$customer, $product] = $this->basics();
        $plan = $this->plan($product, 'monthly', 100);
        $subscription = $this->subscription($customer, $plan, '2026-10-10', '2026-10-31', '2026-10-10');
        $license = $this->license($subscription, '2026-10-31');

        $invoice = app(BillingService::class)->generateInvoiceForSubscription($subscription, Carbon::today());
        app(InvoicePaymentCompletionService::class)->complete($invoice, ['notify' => false]);

        $this->assertSame('2026-10-31', $license->fresh()->expires_at->toDateString());
    }

    #[Test]
    public function paying_a_yearly_invoice_buys_one_year_not_two(): void
    {
        Carbon::setTestNow('2026-10-04 09:00:00');
        [$customer, $product] = $this->basics();
        $plan = $this->plan($product, 'yearly', 1200);
        $subscription = $this->subscription($customer, $plan, '2026-10-04', '2027-10-04', '2026-10-04');
        $license = $this->license($subscription, '2027-10-04');

        $invoice = app(BillingService::class)->generateInvoiceForSubscription($subscription, Carbon::today());
        app(InvoicePaymentCompletionService::class)->complete($invoice, ['notify' => false]);

        $this->assertSame('2027-10-09', $license->fresh()->expires_at->toDateString());
    }

    private function basics(): array
    {
        $customer = Customer::create([
            'name' => 'Regression Customer',
            'status' => 'active',
            'email' => 'regression@example.com',
            'phone' => '01700000000',
        ]);
        $product = Product::create(['name' => 'Regression Product', 'slug' => 'regression-product', 'status' => 'active']);

        return [$customer, $product];
    }

    private function plan(Product $product, string $interval, float $price, array $extra = []): Plan
    {
        return Plan::create(array_merge([
            'product_id' => $product->id,
            'name' => ucfirst($interval).' Plan',
            'slug' => $interval.'-plan-'.uniqid(),
            'interval' => $interval,
            'price' => $price,
            'currency' => 'USD',
            'is_active' => true,
        ], $extra));
    }

    private function subscription(Customer $customer, Plan $plan, string $start, string $end, string $nextInvoiceAt): Subscription
    {
        return Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => $start,
            'current_period_start' => $start,
            'current_period_end' => $end,
            'next_invoice_at' => $nextInvoiceAt,
            'auto_renew' => true,
            'cancel_at_period_end' => false,
        ]);
    }

    private function license(Subscription $subscription, string $expiresAt): License
    {
        return License::create([
            'subscription_id' => $subscription->id,
            'product_id' => $subscription->plan->product_id,
            'license_key' => strtoupper(uniqid('KEY')),
            'status' => 'active',
            'starts_at' => $subscription->start_date->toDateString(),
            'expires_at' => $expiresAt,
            'max_domains' => 1,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }
}
