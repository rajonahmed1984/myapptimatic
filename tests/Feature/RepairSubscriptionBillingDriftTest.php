<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RepairSubscriptionBillingDriftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-04 09:00:00');
        Setting::setValue('invoice_generation_days', 15);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function a_dry_run_reports_without_writing(): void
    {
        [$subscription, $license] = $this->driftedYearlySubscription();

        $this->artisan('subscriptions:repair-billing-drift')->assertSuccessful();

        $this->assertSame('2027-09-19', $subscription->fresh()->next_invoice_at->toDateString());
        $this->assertSame('2027-10-04', $license->fresh()->expires_at->toDateString());
    }

    #[Test]
    public function apply_re_dates_the_renewal_but_leaves_licenses_alone(): void
    {
        [$subscription, $license] = $this->driftedYearlySubscription();

        $this->artisan('subscriptions:repair-billing-drift', ['--apply' => true])->assertSuccessful();

        // The unbilled term started 2026-10-04, so its renewal is due now.
        $this->assertSame('2026-10-04', $subscription->fresh()->next_invoice_at->toDateString());
        $this->assertSame('2027-10-04', $license->fresh()->expires_at->toDateString());
    }

    #[Test]
    public function shorten_licenses_pulls_expiry_back_to_the_paid_period_plus_grace(): void
    {
        $subscription = $this->subscription('monthly', '2026-11-01', '2026-11-30', '2026-11-01');
        $this->paidInvoice($subscription, '2026-10-01', '2026-10-31');
        $license = $this->license($subscription, '2026-11-30');

        $this->artisan('subscriptions:repair-billing-drift', ['--apply' => true, '--shorten-licenses' => true])
            ->assertSuccessful();

        $this->assertSame('2026-11-05', $license->fresh()->expires_at->toDateString());
    }

    #[Test]
    public function auto_renew_is_only_switched_on_for_the_ids_given(): void
    {
        $chosen = $this->subscription('monthly', '2026-10-01', '2026-10-31', '2026-11-01', ['auto_renew' => false]);
        $other = $this->subscription('monthly', '2026-10-01', '2026-10-31', '2026-11-01', ['auto_renew' => false]);

        $this->artisan('subscriptions:repair-billing-drift', ['--apply' => true, '--enable-auto-renew' => (string) $chosen->id])
            ->assertSuccessful();

        $this->assertTrue($chosen->fresh()->auto_renew);
        $this->assertFalse($other->fresh()->auto_renew);
    }

    #[Test]
    public function a_plan_switched_from_monthly_is_reported_not_rebilled(): void
    {
        // A monthly subscription moved onto a yearly plan: the window is
        // still one month and next_invoice_at was set by hand.
        $subscription = $this->subscription('yearly', '2026-09-01', '2026-09-30', '2027-02-01');

        $this->artisan('subscriptions:repair-billing-drift', ['--apply' => true])
            ->expectsOutputToContain('Needs manual review')
            ->expectsOutputToContain('subscription #'.$subscription->id.' (yearly plan)')
            ->assertSuccessful();

        $this->assertSame('2027-02-01', $subscription->fresh()->next_invoice_at->toDateString());
    }

    #[Test]
    public function a_hand_set_date_on_a_full_term_window_is_reported_not_rebilled(): void
    {
        $subscription = $this->subscription('yearly', '2026-10-04', '2027-10-04', '2027-03-01');

        $this->artisan('subscriptions:repair-billing-drift', ['--apply' => true])->assertSuccessful();

        $this->assertSame('2027-03-01', $subscription->fresh()->next_invoice_at->toDateString());
    }

    #[Test]
    public function an_already_billed_term_is_left_alone(): void
    {
        $subscription = $this->subscription('yearly', '2027-10-04', '2028-10-04', '2028-09-19');
        Invoice::create([
            'customer_id' => $subscription->customer_id,
            'subscription_id' => $subscription->id,
            'number' => 'INV-BILLED',
            'status' => 'unpaid',
            'issue_date' => '2027-09-19',
            'due_date' => '2027-10-04',
            'period_start' => '2027-10-04',
            'period_end' => '2028-10-04',
            'subtotal' => 1200,
            'late_fee' => 0,
            'total' => 1200,
            'currency' => 'USD',
        ]);

        $this->artisan('subscriptions:repair-billing-drift', ['--apply' => true])->assertSuccessful();

        $this->assertSame('2028-09-19', $subscription->fresh()->next_invoice_at->toDateString());
    }

    private function driftedYearlySubscription(): array
    {
        // What the old code left behind: last year's term billed and paid,
        // the window rolled on, but the renewal dated near the end of it.
        $subscription = $this->subscription('yearly', '2026-10-04', '2027-10-04', '2027-09-19');
        $this->paidInvoice($subscription, '2025-10-04', '2026-10-04', '2025-10-04');
        $license = $this->license($subscription, '2027-10-04');

        return [$subscription, $license];
    }

    private function subscription(string $interval, string $start, string $end, string $nextInvoiceAt, array $extra = []): Subscription
    {
        $customer = Customer::create(['name' => 'Drift Customer '.uniqid(), 'status' => 'active']);
        $product = Product::create(['name' => 'Drift Product', 'slug' => 'drift-'.uniqid(), 'status' => 'active']);
        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => ucfirst($interval),
            'slug' => $interval.'-'.uniqid(),
            'interval' => $interval,
            'price' => 1200,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        return Subscription::create(array_merge([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => $start,
            'current_period_start' => $start,
            'current_period_end' => $end,
            'next_invoice_at' => $nextInvoiceAt,
            'auto_renew' => true,
            'cancel_at_period_end' => false,
        ], $extra));
    }

    private function paidInvoice(Subscription $subscription, string $periodStart, string $periodEnd, ?string $issueDate = null): Invoice
    {
        return Invoice::create([
            'customer_id' => $subscription->customer_id,
            'subscription_id' => $subscription->id,
            'number' => 'INV-'.uniqid(),
            'status' => 'paid',
            'issue_date' => $issueDate ?? $periodStart,
            'due_date' => $issueDate ?? $periodStart,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'subtotal' => 100,
            'late_fee' => 0,
            'total' => 100,
            'currency' => 'USD',
        ]);
    }

    private function license(Subscription $subscription, string $expiresAt): License
    {
        return License::create([
            'subscription_id' => $subscription->id,
            'product_id' => $subscription->plan->product_id,
            'license_key' => strtoupper(uniqid('DRIFT')),
            'status' => 'active',
            'starts_at' => $subscription->start_date->toDateString(),
            'expires_at' => $expiresAt,
            'max_domains' => 1,
        ]);
    }
}
