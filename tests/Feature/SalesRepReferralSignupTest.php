<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Http\Middleware\CaptureSalesReferral;
use App\Models\Customer;
use App\Models\SalesRepresentative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SalesRepReferralSignupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'recaptcha.enabled' => false,
            'recaptcha.site_key' => null,
            'recaptcha.secret_key' => null,
        ]);
    }

    #[Test]
    public function a_sales_rep_signs_up_on_the_shared_register_page_and_waits_for_approval(): void
    {
        $response = $this->post(route('register.store'), [
            'account_type' => 'sales_rep',
            'name' => 'Rahim Rep',
            'email' => 'rahim@example.com',
            'phone_country' => '+880',
            'phone' => '01711000000',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');
        $this->assertGuest('web');
        $this->assertGuest('sales');

        $user = User::where('email', 'rahim@example.com')->sole();
        $this->assertSame(Role::SALES, $user->role);
        $this->assertNull($user->customer_id);

        $rep = SalesRepresentative::where('user_id', $user->id)->sole();
        $this->assertSame('pending', $rep->status);
        $this->assertSame('+8801711000000', $rep->phone);
        $this->assertNotEmpty($rep->referral_code);
        $this->assertSame(0, Customer::count(), 'A sales rep sign-up creates no customer.');
    }

    #[Test]
    public function a_pending_rep_cannot_sign_in_until_approved_then_uses_the_shared_login(): void
    {
        $rep = $this->rep('pending', 'secret-pass');

        $this->from(route('login'))->post(route('login.attempt'), [
            'email' => $rep->email,
            'password' => 'secret-pass',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Your sales representative account is waiting for admin approval. We will email you once it is approved.']);
        $this->assertGuest('sales');

        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $this->actingAs($admin)
            ->post(route('admin.sales-reps.approve', $rep))
            ->assertSessionHas('status');
        $this->assertSame('active', $rep->fresh()->status);
        $this->post(route('logout'));

        $this->post(route('login.attempt'), [
            'email' => $rep->email,
            'password' => 'secret-pass',
        ])->assertRedirect(route('rep.dashboard'));

        $this->assertAuthenticatedAs($rep->user, 'sales');
    }

    #[Test]
    public function only_a_pending_rep_can_be_approved(): void
    {
        $rep = $this->rep('inactive');
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $this->actingAs($admin)
            ->post(route('admin.sales-reps.approve', $rep))
            ->assertSessionHas('error');

        $this->assertSame('inactive', $rep->fresh()->status);
    }

    #[Test]
    public function a_referral_link_assigns_the_new_customer_to_the_rep(): void
    {
        $rep = $this->rep('active');

        $this->get(route('register', ['ref' => $rep->referral_code]))
            ->assertOk()
            ->assertCookie(CaptureSalesReferral::COOKIE, $rep->referral_code)
            ->assertSee($rep->name);

        $this->withCookie(CaptureSalesReferral::COOKIE, $rep->referral_code)
            ->post(route('register.store'), $this->customerPayload('buyer@example.com'))
            ->assertRedirect(route('client.dashboard'));

        $customer = Customer::where('email', 'buyer@example.com')->sole();
        $this->assertSame($rep->id, $customer->referred_by_sales_rep_id);
        $this->assertSame($rep->id, $customer->default_sales_rep_id, 'Commission follows the default sales rep.');
    }

    #[Test]
    public function the_referral_code_works_on_any_page(): void
    {
        $rep = $this->rep('active');

        $this->get('/login?ref='.strtolower($rep->referral_code))
            ->assertCookie(CaptureSalesReferral::COOKIE, $rep->referral_code);
    }

    #[Test]
    public function an_inactive_or_pending_reps_code_is_ignored(): void
    {
        $rep = $this->rep('pending');

        $this->get(route('register', ['ref' => $rep->referral_code]))
            ->assertCookieMissing(CaptureSalesReferral::COOKIE);

        $this->withCookie(CaptureSalesReferral::COOKIE, $rep->referral_code)
            ->post(route('register.store'), $this->customerPayload('plain@example.com'));

        $customer = Customer::where('email', 'plain@example.com')->sole();
        $this->assertNull($customer->referred_by_sales_rep_id);
        $this->assertNull($customer->default_sales_rep_id);
    }

    #[Test]
    public function a_signed_in_customer_can_still_open_the_sales_rep_form(): void
    {
        $customer = Customer::create(['name' => 'Existing', 'status' => 'active']);
        $client = User::factory()->create(['role' => Role::CLIENT, 'customer_id' => $customer->id]);

        $this->actingAs($client)->get(route('register'))->assertRedirect(route('client.dashboard'));

        $this->actingAs($client)
            ->get(route('register', ['as' => 'sales_rep']))
            ->assertOk()
            ->assertSee('sales_rep');
    }

    #[Test]
    public function the_rep_dashboard_shows_the_referral_link(): void
    {
        $rep = $this->rep('active');
        Customer::create(['name' => 'Referred', 'status' => 'active', 'referred_by_sales_rep_id' => $rep->id]);

        $this->actingAs($rep->user, 'sales')
            ->get(route('rep.dashboard'))
            ->assertOk()
            ->assertSee('register?ref='.$rep->referral_code, false);
    }

    #[Test]
    public function a_referred_customers_subscription_invoice_earns_the_reps_subscription_percentage(): void
    {
        $rep = $this->rep('active');
        $rep->update(['subscription_commission_percentage' => 10]);

        $referred = Customer::create(['name' => 'Referred', 'status' => 'active', 'referred_by_sales_rep_id' => $rep->id, 'default_sales_rep_id' => $rep->id]);
        $assigned = Customer::create(['name' => 'Assigned', 'status' => 'active', 'default_sales_rep_id' => $rep->id]);

        $service = app(\App\Services\CommissionService::class);

        $earning = $service->createOrUpdateEarningOnInvoicePaid($this->paidSubscriptionInvoice($referred, 1500));
        $this->assertSame('150.00', (string) $earning->commission_amount);

        // A customer assigned by an admin, not referred, is priced as before.
        $earning = $service->createOrUpdateEarningOnInvoicePaid($this->paidSubscriptionInvoice($assigned, 1500));
        $this->assertSame('0.00', (string) $earning->commission_amount);
    }

    #[Test]
    public function approving_can_set_the_reps_subscription_percentage(): void
    {
        $rep = $this->rep('pending');
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $this->actingAs($admin)
            ->post(route('admin.sales-reps.approve', $rep), ['subscription_commission_percentage' => '12.5'])
            ->assertSessionHas('status');

        $this->assertEquals(12.5, (float) $rep->fresh()->subscription_commission_percentage);
    }

    #[Test]
    public function the_affiliate_module_is_gone_and_its_links_redirect(): void
    {
        $this->assertFalse(Schema::hasTable('affiliates'));
        $this->assertFalse(Schema::hasColumn('customers', 'referred_by_affiliate_id'));

        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $this->actingAs($admin)->get('/admin/affiliates/payouts')->assertRedirect(route('admin.sales-reps.index'));
    }

    private function rep(string $status, string $password = 'password'): SalesRepresentative
    {
        $user = User::factory()->create(['role' => Role::SALES, 'password' => $password]);

        return SalesRepresentative::create([
            'user_id' => $user->id,
            'name' => 'Rep '.$user->id,
            'email' => $user->email,
            'status' => $status,
        ]);
    }

    private function paidSubscriptionInvoice(Customer $customer, float $total): \App\Models\Invoice
    {
        $product = \App\Models\Product::create(['name' => 'Product', 'slug' => 'product-'.uniqid(), 'status' => 'active']);
        $plan = \App\Models\Plan::create([
            'product_id' => $product->id,
            'name' => 'Monthly',
            'slug' => 'monthly-'.uniqid(),
            'interval' => 'monthly',
            'price' => $total,
            'currency' => 'BDT',
            'is_active' => true,
        ]);
        $subscription = \App\Models\Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => '2026-10-01',
            'current_period_start' => '2026-11-01',
            'current_period_end' => '2026-11-30',
            'next_invoice_at' => '2026-11-01',
        ]);

        return \App\Models\Invoice::create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'number' => 'INV-'.uniqid(),
            'status' => 'paid',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-01',
            'subtotal' => $total,
            'late_fee' => 0,
            'total' => $total,
            'currency' => 'BDT',
        ]);
    }

    private function customerPayload(string $email): array
    {
        return [
            'account_type' => 'customer',
            'name' => 'New Buyer',
            'email' => $email,
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'currency' => 'BDT',
        ];
    }
}
