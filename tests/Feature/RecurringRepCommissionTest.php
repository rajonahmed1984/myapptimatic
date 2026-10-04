<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CommissionEarning;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectMaintenance;
use App\Models\SalesRepresentative;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\SalesRepStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecurringRepCommissionTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::create(['name' => 'Client', 'status' => 'active']);
    }

    #[Test]
    public function every_paid_maintenance_invoice_pays_each_rep_their_monthly_amount(): void
    {
        $osman = $this->rep('Osman');
        $rahim = $this->rep('Rahim');
        $maintenance = $this->maintenance(3000, [$osman->id => 300, $rahim->id => 150]);
        $service = app(CommissionService::class);

        foreach (['2026-08-01', '2026-09-01', '2026-10-01'] as $month) {
            $service->createOrUpdateEarningOnInvoicePaid($this->paidMaintenanceInvoice($maintenance, 3000, $month));
        }

        $this->assertSame(900.0, (float) CommissionEarning::where('sales_representative_id', $osman->id)->sum('commission_amount'));
        $this->assertSame(450.0, (float) CommissionEarning::where('sales_representative_id', $rahim->id)->sum('commission_amount'));
        $this->assertSame(6, CommissionEarning::where('source_type', 'project_maintenance')->where('status', 'payable')->count());

        // It is client-paid commission, so it counts on the rep's statement under the project.
        $statement = app(SalesRepStatementService::class)->forRep($osman->id);
        $this->assertSame(900.0, $statement['commission_earned']);
        $this->assertSame('project:'.$maintenance->project_id, $statement['lines'][0]['key']);
    }

    #[Test]
    public function paying_the_same_invoice_twice_does_not_pay_twice(): void
    {
        $osman = $this->rep('Osman');
        $maintenance = $this->maintenance(3000, [$osman->id => 300]);
        $invoice = $this->paidMaintenanceInvoice($maintenance, 3000, '2026-10-01');
        $service = app(CommissionService::class);

        $service->createOrUpdateEarningOnInvoicePaid($invoice);
        $service->createOrUpdateEarningOnInvoicePaid($invoice);

        $this->assertSame(1, CommissionEarning::count());
    }

    #[Test]
    public function maintenance_commission_never_exceeds_what_the_invoice_charged(): void
    {
        $osman = $this->rep('Osman');
        $maintenance = $this->maintenance(3000, [$osman->id => 300]);

        app(CommissionService::class)->createOrUpdateEarningOnInvoicePaid($this->paidMaintenanceInvoice($maintenance, 100, '2026-10-01'));

        $this->assertSame('100.00', (string) CommissionEarning::sole()->commission_amount);
    }

    #[Test]
    public function an_empty_plan_earning_left_from_before_is_retired(): void
    {
        $osman = $this->rep('Osman');
        $maintenance = $this->maintenance(3000, [$osman->id => 300]);
        $invoice = $this->paidMaintenanceInvoice($maintenance, 3000, '2026-10-01');
        $old = CommissionEarning::create([
            'sales_representative_id' => $osman->id,
            'source_type' => 'plan',
            'source_id' => $invoice->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'currency' => 'BDT',
            'paid_amount' => 3000,
            'commission_amount' => 0,
            'status' => 'payable',
            'idempotency_key' => 'invoice:'.$invoice->id.':rep:'.$osman->id.':source:plan',
        ]);

        app(CommissionService::class)->createOrUpdateEarningOnInvoicePaid($invoice);

        $this->assertSame('reversed', $old->fresh()->status);
        $this->assertSame('300.00', (string) CommissionEarning::where('source_type', 'project_maintenance')->sole()->commission_amount);
    }

    #[Test]
    public function a_rep_assigned_without_a_commission_amount_earns_their_subscriptions_percentage(): void
    {
        $osman = $this->rep('Osman', subscriptionPercent: 10);
        $subscription = $this->subscription($osman, commissionAmount: null);

        $earning = app(CommissionService::class)->createOrUpdateEarningOnInvoicePaid($this->paidSubscriptionInvoice($subscription, 2000));

        $this->assertSame('200.00', (string) $earning->commission_amount);
    }

    #[Test]
    public function a_commission_amount_on_the_subscription_still_wins(): void
    {
        $osman = $this->rep('Osman', subscriptionPercent: 10);
        $subscription = $this->subscription($osman, commissionAmount: 300);

        $earning = app(CommissionService::class)->createOrUpdateEarningOnInvoicePaid($this->paidSubscriptionInvoice($subscription, 2000));

        $this->assertSame('300.00', (string) $earning->commission_amount);
    }

    #[Test]
    public function the_subscription_form_carries_each_reps_percentage(): void
    {
        $this->rep('Osman', subscriptionPercent: 10);
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $this->actingAs($admin)
            ->get(route('admin.subscriptions.create'))
            ->assertInertia(fn ($page) => $page->where('sales_reps.0.subscription_commission_percentage', 10));
    }

    private function rep(string $name, ?float $subscriptionPercent = null): SalesRepresentative
    {
        $user = User::factory()->create(['role' => Role::SALES]);

        return SalesRepresentative::create([
            'user_id' => $user->id,
            'name' => $name,
            'email' => $user->email,
            'status' => 'active',
            'subscription_commission_percentage' => $subscriptionPercent,
        ]);
    }

    /**
     * @param  array<int, float>  $repAmounts  rep id => monthly commission
     */
    private function maintenance(float $amount, array $repAmounts): ProjectMaintenance
    {
        $project = Project::create(['customer_id' => $this->customer->id, 'name' => 'Website', 'status' => 'complete', 'currency' => 'BDT']);
        $maintenance = ProjectMaintenance::create([
            'project_id' => $project->id,
            'customer_id' => $this->customer->id,
            'title' => 'Monthly maintenance',
            'amount' => $amount,
            'currency' => 'BDT',
            'billing_cycle' => 'monthly',
            'start_date' => '2026-08-01',
            'next_billing_date' => '2026-11-01',
            'status' => 'active',
        ]);

        foreach ($repAmounts as $repId => $repAmount) {
            DB::table('project_maintenance_sales_representative')->insert([
                'project_maintenance_id' => $maintenance->id,
                'sales_representative_id' => $repId,
                'amount' => $repAmount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $maintenance;
    }

    private function paidMaintenanceInvoice(ProjectMaintenance $maintenance, float $total, string $date): Invoice
    {
        return Invoice::create([
            'customer_id' => $this->customer->id,
            'maintenance_id' => $maintenance->id,
            'number' => 'INV-'.uniqid(),
            'status' => 'paid',
            'issue_date' => $date,
            'due_date' => $date,
            'subtotal' => $total,
            'late_fee' => 0,
            'total' => $total,
            'currency' => 'BDT',
        ]);
    }

    private function subscription(SalesRepresentative $rep, ?float $commissionAmount): Subscription
    {
        $product = Product::create(['name' => 'Sectorix', 'slug' => 'sectorix-'.uniqid(), 'status' => 'active']);
        $plan = Plan::create(['product_id' => $product->id, 'name' => 'Monthly', 'slug' => 'm-'.uniqid(), 'interval' => 'monthly', 'price' => 2000, 'currency' => 'BDT', 'is_active' => true]);

        return Subscription::create([
            'customer_id' => $this->customer->id,
            'plan_id' => $plan->id,
            'sales_rep_id' => $rep->id,
            'sales_rep_commission_amount' => $commissionAmount,
            'status' => 'active',
            'start_date' => '2026-10-01',
            'current_period_start' => '2026-11-01',
            'current_period_end' => '2026-11-30',
            'next_invoice_at' => '2026-11-01',
        ]);
    }

    private function paidSubscriptionInvoice(Subscription $subscription, float $total): Invoice
    {
        return Invoice::create([
            'customer_id' => $this->customer->id,
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
}
