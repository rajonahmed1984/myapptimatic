<?php

namespace Tests\Feature;

use App\Models\AccountingEntry;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubscriptionPlanChangeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    private Product $product;

    private Plan $monthly;

    private Plan $yearly;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Setting::setValue('currency', 'BDT');
        Setting::setValue('invoice_due_days', 7);
        Setting::setValue('invoice_generation_days', 7);

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->customer = Customer::create(['name' => 'Plan Change Customer', 'status' => 'active']);
        $this->product = Product::create(['name' => 'Sectorix', 'slug' => 'sectorix-'.uniqid(), 'status' => 'active']);
        $this->monthly = $this->plan('Wholesale', 'monthly', 1500);
        $this->yearly = $this->plan('Wholesale Yearly', 'yearly', 18000);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function monthly_to_yearly_at_next_renewal_starts_the_year_on_the_first_unbilled_day(): void
    {
        Carbon::setTestNow('2026-08-20 10:00:00');
        // August is invoiced; the window already points at September.
        $subscription = $this->subscription($this->monthly, 1500, '2026-09-01', '2026-09-30', '2026-09-01');
        $this->invoice($subscription, '2026-08-01', '2026-08-31', 1500, 'paid');

        $this->actingAs($this->admin)
            ->put(route('admin.subscriptions.change-plan', $subscription), [
                'plan_id' => $this->yearly->id,
                'timing' => 'next_renewal',
            ])
            ->assertSessionHas('status');

        $subscription->refresh();
        $this->assertSame($this->yearly->id, $subscription->plan_id);
        $this->assertSame('18000.00', (string) $subscription->subscription_amount);
        $this->assertSame('2026-09-01', $subscription->current_period_start->toDateString());
        $this->assertSame('2027-09-01', $subscription->current_period_end->toDateString());
        $this->assertSame('2026-09-01', $subscription->next_invoice_at->toDateString());
        $this->assertSame(1, Invoice::where('subscription_id', $subscription->id)->count(), 'Nothing is billed now.');

        // The renewal then bills one full year.
        Carbon::setTestNow('2026-09-01 06:00:00');
        $invoice = app(BillingService::class)->generateInvoiceForSubscription($subscription->fresh(), Carbon::today());
        $this->assertSame('18000.00', (string) $invoice->subtotal);
        $this->assertSame('2026-09-01', $invoice->period_start->toDateString());
        $this->assertSame('2027-09-01', $invoice->period_end->toDateString());
    }

    #[Test]
    public function yearly_to_monthly_at_next_renewal_switches_after_the_paid_year(): void
    {
        Carbon::setTestNow('2026-12-20 10:00:00');
        $subscription = $this->subscription($this->yearly, 18000, '2027-01-15', '2028-01-15', '2027-01-08');
        $this->invoice($subscription, '2026-01-15', '2027-01-15', 18000, 'paid');

        $this->actingAs($this->admin)
            ->put(route('admin.subscriptions.change-plan', $subscription), [
                'plan_id' => $this->monthly->id,
                'timing' => 'next_renewal',
            ])
            ->assertSessionHas('status');

        $subscription->refresh();
        $this->assertSame('2027-01-15', $subscription->current_period_start->toDateString());
        $this->assertSame('2027-01-31', $subscription->current_period_end->toDateString());
        $this->assertSame('2027-01-15', $subscription->next_invoice_at->toDateString());

        // The first monthly window is part of January, charged by the day.
        Carbon::setTestNow('2027-01-15 06:00:00');
        $invoice = app(BillingService::class)->generateInvoiceForSubscription($subscription->fresh(), Carbon::today());
        $this->assertSame(round(1500 * 17 / 31, 2), (float) $invoice->subtotal);
    }

    #[Test]
    public function monthly_to_yearly_now_bills_the_year_and_credits_unused_paid_days(): void
    {
        Carbon::setTestNow('2026-10-11 10:00:00');
        $subscription = $this->subscription($this->monthly, 1500, '2026-11-01', '2026-11-30', '2026-11-01');
        $october = $this->invoice($subscription, '2026-10-01', '2026-10-31', 1500, 'paid', withPayment: true);
        $license = License::create([
            'subscription_id' => $subscription->id,
            'product_id' => $this->product->id,
            'license_key' => 'PLANCHANGE1',
            'status' => 'active',
            'starts_at' => '2026-01-01',
            'expires_at' => '2026-11-05',
            'max_domains' => 1,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.subscriptions.change-plan', $subscription), [
                'plan_id' => $this->yearly->id,
                'timing' => 'now',
            ])
            ->assertSessionHas('status');

        $new = Invoice::where('subscription_id', $subscription->id)->where('id', '!=', $october->id)->sole();
        $this->assertSame('2026-10-11', $new->period_start->toDateString());
        $this->assertSame('2027-10-11', $new->period_end->toDateString());
        $this->assertSame('18000.00', (string) $new->total);
        $this->assertSame('unpaid', $new->status);

        // 21 of October's 31 days (11th–31st) are unused.
        $credit = (float) AccountingEntry::where('invoice_id', $new->id)->where('type', 'credit')->sum('amount');
        $this->assertSame(round(1500 * 21 / 31, 2), $credit);

        $subscription->refresh();
        $this->assertSame('2027-10-11', $subscription->current_period_start->toDateString());
        $this->assertSame('2027-10-04', $subscription->next_invoice_at->toDateString());
        $this->assertSame('2026-11-05', $license->fresh()->expires_at->toDateString(), 'Unchanged until the year is paid.');
    }

    #[Test]
    public function an_immediate_switch_cancels_unpaid_invoices_for_periods_not_started(): void
    {
        Carbon::setTestNow('2026-10-25 10:00:00');
        // November was billed ahead on the 22nd and is unpaid.
        $subscription = $this->subscription($this->monthly, 1500, '2026-12-01', '2026-12-31', '2026-12-01');
        $this->invoice($subscription, '2026-10-01', '2026-10-31', 1500, 'paid');
        $november = $this->invoice($subscription, '2026-11-01', '2026-11-30', 1500, 'unpaid', issue: '2026-10-22');

        $this->actingAs($this->admin)
            ->put(route('admin.subscriptions.change-plan', $subscription), [
                'plan_id' => $this->yearly->id,
                'timing' => 'now',
            ])
            ->assertSessionHas('status');

        $this->assertSame('cancelled', $november->fresh()->status);
    }

    #[Test]
    public function an_immediate_switch_is_refused_while_the_current_period_is_unpaid(): void
    {
        Carbon::setTestNow('2026-10-11 10:00:00');
        $subscription = $this->subscription($this->monthly, 1500, '2026-11-01', '2026-11-30', '2026-11-01');
        $this->invoice($subscription, '2026-10-01', '2026-10-31', 1500, 'unpaid');

        $this->actingAs($this->admin)
            ->put(route('admin.subscriptions.change-plan', $subscription), [
                'plan_id' => $this->yearly->id,
                'timing' => 'now',
            ])
            ->assertSessionHas('error');

        $this->assertSame($this->monthly->id, $subscription->fresh()->plan_id);
    }

    #[Test]
    public function yearly_to_monthly_now_is_refused_when_the_credit_exceeds_the_first_month(): void
    {
        Carbon::setTestNow('2026-03-10 10:00:00');
        $subscription = $this->subscription($this->yearly, 18000, '2027-01-15', '2028-01-15', '2027-01-08');
        $this->invoice($subscription, '2026-01-15', '2027-01-15', 18000, 'paid');

        $this->actingAs($this->admin)
            ->getJson(route('admin.subscriptions.change-plan.preview', [
                'subscription' => $subscription,
                'plan_id' => $this->monthly->id,
                'timing' => 'now',
            ]))
            ->assertOk()
            ->assertJsonPath('data.blocked_reason', fn ($reason) => str_contains((string) $reason, 'next renewal'));

        $this->actingAs($this->admin)
            ->put(route('admin.subscriptions.change-plan', $subscription), [
                'plan_id' => $this->monthly->id,
                'timing' => 'now',
            ])
            ->assertSessionHas('error');

        $this->assertSame($this->yearly->id, $subscription->fresh()->plan_id);
    }

    #[Test]
    public function the_preview_describes_the_change_without_making_it(): void
    {
        Carbon::setTestNow('2026-08-20 10:00:00');
        $subscription = $this->subscription($this->monthly, 1500, '2026-09-01', '2026-09-30', '2026-09-01');

        $this->actingAs($this->admin)
            ->getJson(route('admin.subscriptions.change-plan.preview', [
                'subscription' => $subscription,
                'plan_id' => $this->yearly->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.interval_changes', true)
            ->assertJsonPath('data.term_start', '2026-09-01')
            ->assertJsonPath('data.term_end', '2027-09-01')
            ->assertJsonPath('data.blocked_reason', null);

        $this->assertSame($this->monthly->id, $subscription->fresh()->plan_id);
    }

    #[Test]
    public function the_edit_form_refuses_to_switch_billing_cycle(): void
    {
        $subscription = $this->subscription($this->monthly, 1500, '2026-09-01', '2026-09-30', '2026-09-01');

        $this->actingAs($this->admin)->put(route('admin.subscriptions.update', $subscription), [
            'customer_id' => $this->customer->id,
            'plan_id' => $this->yearly->id,
            'status' => 'active',
            'start_date' => '01-01-2026',
            'current_period_start' => '01-09-2026',
            'current_period_end' => '30-09-2026',
            'next_invoice_at' => '01-09-2026',
            'auto_renew' => '1',
        ])->assertSessionHasErrors('plan_id');

        $this->assertSame($this->monthly->id, $subscription->fresh()->plan_id);
    }

    #[Test]
    public function a_term_billed_late_is_due_on_the_day_it_is_issued(): void
    {
        Carbon::setTestNow('2026-10-04 10:00:00');
        $subscription = $this->subscription($this->yearly, 18000, '2026-06-01', '2027-06-01', '2026-06-01');

        $invoice = app(BillingService::class)->generateInvoiceForSubscription($subscription, Carbon::today());

        $this->assertSame('2026-10-04', $invoice->due_date->toDateString());
    }

    #[Test]
    public function realign_re_fits_a_window_left_monthly_after_a_switch(): void
    {
        Carbon::setTestNow('2026-10-04 10:00:00');
        $subscription = $this->subscription($this->yearly, 16200, '2026-06-01', '2026-06-30', '2026-12-01');

        $this->artisan('subscriptions:repair-billing-drift', ['--apply' => true, '--realign' => (string) $subscription->id])
            ->assertSuccessful();

        $subscription->refresh();
        $this->assertSame('2026-06-01', $subscription->current_period_start->toDateString());
        $this->assertSame('2027-06-01', $subscription->current_period_end->toDateString());
        $this->assertSame('2026-06-01', $subscription->next_invoice_at->toDateString());
    }

    private function plan(string $name, string $interval, float $price): Plan
    {
        return Plan::create([
            'product_id' => $this->product->id,
            'name' => $name,
            'slug' => 'plan-'.uniqid(),
            'interval' => $interval,
            'price' => $price,
            'currency' => 'BDT',
            'is_active' => true,
        ]);
    }

    private function subscription(Plan $plan, float $amount, string $start, string $end, string $nextInvoiceAt): Subscription
    {
        return Subscription::create([
            'customer_id' => $this->customer->id,
            'plan_id' => $plan->id,
            'subscription_amount' => $amount,
            'status' => 'active',
            'start_date' => '2026-01-01',
            'current_period_start' => $start,
            'current_period_end' => $end,
            'next_invoice_at' => $nextInvoiceAt,
            'auto_renew' => true,
            'cancel_at_period_end' => false,
        ]);
    }

    private function invoice(
        Subscription $subscription,
        string $periodStart,
        string $periodEnd,
        float $total,
        string $status,
        bool $withPayment = false,
        ?string $issue = null
    ): Invoice {
        $invoice = Invoice::create([
            'customer_id' => $subscription->customer_id,
            'subscription_id' => $subscription->id,
            'number' => 'INV-'.uniqid(),
            'status' => $status,
            'issue_date' => $issue ?? $periodStart,
            'due_date' => $periodStart,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'subtotal' => $total,
            'late_fee' => 0,
            'total' => $total,
            'currency' => 'BDT',
            'paid_at' => $status === 'paid' ? $periodStart : null,
        ]);

        if ($withPayment) {
            AccountingEntry::create([
                'entry_date' => $periodStart,
                'type' => 'payment',
                'amount' => $total,
                'currency' => 'BDT',
                'description' => 'Payment',
                'customer_id' => $subscription->customer_id,
                'invoice_id' => $invoice->id,
            ]);
        }

        return $invoice;
    }
}
