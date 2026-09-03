<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Subscription;
use App\Services\BillingService;
use App\Services\InvoicePaymentCompletionService;
use App\Services\StatusUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers the two ways a paying customer used to lose access:
 *  - the renewal never moved `licenses.expires_at`, so the expiry sweep
 *    revoked licenses that were fully paid up;
 *  - a license suspended while its subscription was still active had no
 *    automated way back, because every unsuspend path keyed on the
 *    subscription's status rather than the balance owed.
 */
class LicenseRenewalLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function paying_each_renewal_keeps_the_license_active(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 09:00:00'));
        [$customer, $subscription] = $this->createSetup();

        $license = $this->createLicense($subscription, [
            'expires_at' => $subscription->current_period_end->toDateString(),
        ]);

        // Drive it the way production does: the nightly cycle issues whatever
        // is due, and the customer settles it the same day.
        foreach (['2026-02-01', '2026-03-01', '2026-04-01'] as $day) {
            Carbon::setTestNow(Carbon::parse($day.' 00:05:00'));

            $this->artisan('billing:run')->assertSuccessful();

            Invoice::query()
                ->where('subscription_id', $subscription->id)
                ->whereIn('status', ['unpaid', 'overdue'])
                ->get()
                ->each(fn (Invoice $invoice) => app(InvoicePaymentCompletionService::class)
                    ->complete($invoice, ['notify' => false]));
        }

        $this->assertGreaterThan(
            1,
            Invoice::where('subscription_id', $subscription->id)->count(),
            'Renewal invoices should have been generated across the three months.'
        );

        $subscription->refresh();
        $license->refresh();

        $this->assertSame(
            $subscription->current_period_end->toDateString(),
            $license->expires_at->toDateString(),
            'License expiry must track the period the customer has paid for.'
        );

        // The nightly sweep must now leave it alone.
        Carbon::setTestNow(Carbon::parse('2026-04-05 00:05:00'));
        app(StatusUpdateService::class)->updateLicenseExpiryStatus(Carbon::today());

        $this->assertSame('active', $license->fresh()->status);
    }

    #[Test]
    public function an_expired_license_reactivates_when_the_renewal_is_paid(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-05 00:05:00'));
        [$customer, $subscription] = $this->createSetup();

        $license = $this->createLicense($subscription, [
            'status' => 'active',
            'expires_at' => '2026-01-31',
        ]);

        // Nightly sweep: lapsed, but recoverable — not revoked.
        app(StatusUpdateService::class)->updateLicenseExpiryStatus(Carbon::today());
        $this->assertSame('expired', $license->fresh()->status);

        $invoice = app(BillingService::class)
            ->generateInvoiceForSubscription($subscription->fresh(), Carbon::today());
        $this->assertNotNull($invoice);

        app(InvoicePaymentCompletionService::class)->complete($invoice, ['notify' => false]);

        $this->assertSame('active', $license->fresh()->status);
        $this->assertTrue(
            $license->fresh()->expires_at->greaterThanOrEqualTo(Carbon::today()),
            'A reactivated license must be paid up to a future date.'
        );
    }

    #[Test]
    public function paying_restores_a_license_suspended_while_the_subscription_stayed_active(): void
    {
        Setting::setValue('enable_unsuspension', 1);
        Carbon::setTestNow(Carbon::parse('2026-05-10 00:05:00'));

        [$customer, $subscription] = $this->createSetup();
        $license = $this->createLicense($subscription, [
            'status' => 'suspended',
            'expires_at' => '2027-01-01',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'number' => 'INV-RESTORE-1',
            'status' => 'overdue',
            'issue_date' => '2026-05-01',
            'due_date' => '2026-05-05',
            'subtotal' => 100,
            'late_fee' => 0,
            'total' => 100,
            'currency' => 'USD',
        ]);

        $this->assertSame('active', $subscription->fresh()->status);

        app(InvoicePaymentCompletionService::class)->complete($invoice, ['notify' => false]);

        $this->assertSame('active', $license->fresh()->status);
    }

    #[Test]
    public function a_license_stays_suspended_while_another_invoice_is_still_open(): void
    {
        Setting::setValue('enable_unsuspension', 1);
        Carbon::setTestNow(Carbon::parse('2026-05-10 00:05:00'));

        [$customer, $subscription] = $this->createSetup();
        $license = $this->createLicense($subscription, [
            'status' => 'suspended',
            'expires_at' => '2027-01-01',
        ]);

        $paid = Invoice::create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'number' => 'INV-PARTIAL-1',
            'status' => 'overdue',
            'issue_date' => '2026-05-01',
            'due_date' => '2026-05-05',
            'subtotal' => 100,
            'late_fee' => 0,
            'total' => 100,
            'currency' => 'USD',
        ]);

        Invoice::create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'number' => 'INV-PARTIAL-2',
            'status' => 'unpaid',
            'issue_date' => '2026-04-01',
            'due_date' => '2026-04-05',
            'subtotal' => 100,
            'late_fee' => 0,
            'total' => 100,
            'currency' => 'USD',
        ]);

        app(InvoicePaymentCompletionService::class)->complete($paid, ['notify' => false]);

        $this->assertSame('suspended', $license->fresh()->status);
    }

    #[Test]
    public function going_overdue_does_not_suspend_licenses_on_its_own(): void
    {
        // suspend_days is what decides this, and it is the suspension step's
        // job — the overdue sweep used to ignore it entirely.
        Setting::setValue('enable_suspension', 1);
        Setting::setValue('suspend_days', 10);
        Carbon::setTestNow(Carbon::parse('2026-05-10 00:05:00'));

        [$customer, $subscription] = $this->createSetup();
        $license = $this->createLicense($subscription, ['expires_at' => '2027-01-01']);

        Invoice::create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'number' => 'INV-OVERDUE-1',
            'status' => 'unpaid',
            'issue_date' => '2026-05-01',
            'due_date' => '2026-05-05',
            'subtotal' => 100,
            'late_fee' => 0,
            'total' => 100,
            'currency' => 'USD',
        ]);

        app(StatusUpdateService::class)->updateInvoiceOverdueStatus(Carbon::today());

        $this->assertSame('active', $license->fresh()->status);
        $this->assertSame('active', $subscription->fresh()->status);
    }

    /**
     * @return array{0: Customer, 1: Subscription}
     */
    private function createSetup(): array
    {
        $customer = Customer::create([
            'name' => 'Renewal Customer',
            'email' => 'renewal-'.Str::random(6).'@example.test',
            'status' => 'active',
        ]);

        $product = Product::create([
            'name' => 'Renewal Product',
            'slug' => 'renewal-product-'.Str::lower(Str::random(8)),
            'status' => 'active',
        ]);

        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Renewal Plan',
            'slug' => 'renewal-plan-'.Str::lower(Str::random(8)),
            'interval' => 'monthly',
            'price' => 100,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => '2026-01-01',
            'current_period_start' => '2026-01-01',
            'current_period_end' => '2026-01-31',
            'next_invoice_at' => '2026-01-01',
            'auto_renew' => true,
            'cancel_at_period_end' => false,
        ]);

        return [$customer, $subscription];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createLicense(Subscription $subscription, array $attributes = []): License
    {
        return License::create(array_merge([
            'subscription_id' => $subscription->id,
            'product_id' => $subscription->plan->product_id,
            'license_key' => strtoupper(Str::random(24)),
            'status' => 'active',
            'starts_at' => '2026-01-01',
            'max_domains' => 1,
        ], $attributes));
    }
}
