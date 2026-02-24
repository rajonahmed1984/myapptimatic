<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AccountingEntry;
use App\Models\CommissionPayout;
use App\Models\Customer;
use App\Models\License;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Product;
use App\Models\SalesRepresentative;
use App\Models\Subscription;
use App\Models\SupportTicket;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminFormShowUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function migrated_admin_form_and_show_routes_render_inertia_components(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $customer = Customer::create([
            'name' => 'Parity Customer',
            'email' => 'parity.customer@example.test',
            'status' => 'active',
        ]);

        $product = Product::create([
            'name' => 'Parity Product',
            'slug' => 'parity-product',
            'status' => 'active',
        ]);

        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Parity Plan',
            'slug' => 'parity-plan',
            'interval' => 'monthly',
            'price' => 99.00,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'current_period_start' => now()->startOfMonth()->toDateString(),
            'current_period_end' => now()->endOfMonth()->toDateString(),
            'next_invoice_at' => now()->toDateString(),
            'auto_renew' => true,
            'cancel_at_period_end' => false,
        ]);

        $license = License::create([
            'subscription_id' => $subscription->id,
            'product_id' => $product->id,
            'license_key' => 'PARITY-LICENSE-KEY-123',
            'status' => 'active',
            'starts_at' => now()->toDateString(),
            'max_domains' => 1,
        ]);

        $paymentGateway = PaymentGateway::create([
            'name' => 'Parity Gateway',
            'slug' => 'parity-gateway',
            'driver' => 'manual',
            'is_active' => true,
            'sort_order' => 1,
            'settings' => [],
        ]);

        $salesUser = User::factory()->create([
            'role' => Role::SALES,
        ]);

        $salesRep = SalesRepresentative::create([
            'user_id' => $salesUser->id,
            'name' => 'Parity Rep',
            'status' => 'active',
        ]);

        $payout = CommissionPayout::create([
            'sales_representative_id' => $salesRep->id,
            'total_amount' => 50,
            'currency' => 'BDT',
            'status' => 'draft',
        ]);

        $ticket = SupportTicket::create([
            'customer_id' => $customer->id,
            'subject' => 'Parity Ticket',
            'status' => 'open',
            'priority' => 'medium',
            'last_reply_at' => now(),
        ]);

        $paymentMethod = PaymentMethod::create([
            'name' => 'Parity Method',
            'code' => 'parity',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $taxRate = TaxRate::create([
            'name' => 'Parity VAT',
            'rate_percent' => 15,
            'effective_from' => now()->toDateString(),
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'P-10001',
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'subscription_id' => $subscription->id,
            'status' => 'pending',
        ]);

        $entry = AccountingEntry::create([
            'entry_date' => now()->toDateString(),
            'type' => 'payment',
            'amount' => 10.00,
            'currency' => 'USD',
            'customer_id' => $customer->id,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.products.create'))
            ->assertOk()
            ->assertSee('Admin\\/Products\\/Form', false);

        $this->actingAs($admin)
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('Admin\\/Products\\/Form', false);

        $this->actingAs($admin)
            ->get(route('admin.plans.create'))
            ->assertOk()
            ->assertSee('Admin\\/Plans\\/Form', false);

        $this->actingAs($admin)
            ->get(route('admin.plans.edit', $plan))
            ->assertOk()
            ->assertSee('Admin\\/Plans\\/Form', false);

        $this->actingAs($admin)
            ->get(route('admin.subscriptions.create'))
            ->assertOk()
            ->assertSee('Admin\\/Subscriptions\\/Form', false);

        $this->actingAs($admin)
            ->get(route('admin.subscriptions.edit', $subscription))
            ->assertOk()
            ->assertSee('Admin\\/Subscriptions\\/Form', false);

        $this->actingAs($admin)
            ->get(route('admin.licenses.create'))
            ->assertOk()
            ->assertSee('Admin\\/Licenses\\/Form', false);

        $this->actingAs($admin)
            ->get(route('admin.licenses.edit', $license))
            ->assertOk()
            ->assertSee('Admin\\/Licenses\\/Form', false);

        $this->actingAs($admin)
            ->get(route('admin.payment-gateways.edit', $paymentGateway))
            ->assertOk()
            ->assertSee('Admin\\/PaymentGateways\\/Edit', false);

        $this->actingAs($admin)
            ->get(route('admin.commission-payouts.create'))
            ->assertOk()
            ->assertSee('Admin\\/CommissionPayouts\\/Create', false);

        $this->actingAs($admin)
            ->get(route('admin.commission-payouts.show', $payout))
            ->assertOk()
            ->assertSee('Admin\\/CommissionPayouts\\/Show', false);

        $this->actingAs($admin)
            ->get(route('admin.support-tickets.create'))
            ->assertOk()
            ->assertSee('Admin\\/SupportTickets\\/Create', false);

        $this->actingAs($admin)
            ->get(route('admin.support-tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Admin\\/SupportTickets\\/Show', false);

        $this->actingAs($admin)
            ->get(route('admin.finance.payment-methods.show', $paymentMethod))
            ->assertOk()
            ->assertSee('Admin\\/Finance\\/PaymentMethods\\/Show', false);

        $this->actingAs($admin)
            ->get(route('admin.finance.tax.rates.edit', $taxRate))
            ->assertOk()
            ->assertSee('Admin\\/Finance\\/Tax\\/EditRate', false);

        $this->actingAs($admin)
            ->get(route('admin.accounting.create'))
            ->assertOk()
            ->assertSee('Admin\\/Accounting\\/Form', false);

        $this->actingAs($admin)
            ->get(route('admin.accounting.edit', $entry))
            ->assertOk()
            ->assertSee('Admin\\/Accounting\\/Form', false);

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Admin\\/Orders\\/Show', false);
    }
}
