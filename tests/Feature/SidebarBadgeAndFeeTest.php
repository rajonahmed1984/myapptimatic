<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AccountingEntry;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Two UI-facing regressions: the sidebar badge counts never reached the page,
 * and the transaction fee typed into "Add Payment" was thrown away.
 */
class SidebarBadgeAndFeeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sidebar_badge_counts_are_shared_with_the_page(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create(['name' => 'Badge Client', 'status' => 'active']);

        $product = \App\Models\Product::create([
            'name' => 'Badge Product',
            'slug' => 'badge-product',
            'status' => 'active',
        ]);

        $plan = \App\Models\Plan::create([
            'product_id' => $product->id,
            'name' => 'Badge Plan',
            'slug' => 'badge-plan',
            'interval' => 'monthly',
            'price' => 100,
            'currency' => 'BDT',
            'is_active' => true,
        ]);

        Order::create([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => 'pending',
            'total' => 100,
            'currency' => 'BDT',
        ]);

        SupportTicket::create([
            'customer_id' => $customer->id,
            'subject' => 'Needs help',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        // Any admin page will do — the stats ride on every Inertia response.
        // (Not the dashboard: its hourly-orders chart uses MySQL's HOUR(),
        // which SQLite does not have.)
        $response = $this->actingAs($admin)->get(route('admin.customers.index'));
        $response->assertOk();

        $stats = $response->viewData('page')['props']['stats'] ?? null;

        $this->assertIsArray($stats, 'Inertia props must carry the sidebar stats.');
        $this->assertSame(1, $stats['admin']['pending_orders'] ?? null);
        $this->assertSame(1, $stats['admin']['open_support_tickets'] ?? null);
    }

    #[Test]
    public function the_header_carries_all_three_action_counts_even_at_zero(): void
    {
        // The header chips render unconditionally now; they used to be hidden
        // at zero, which read as the Pending Orders and Support Tickets
        // facilities having disappeared.
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $response = $this->actingAs($admin)->get(route('admin.customers.index'));
        $response->assertOk();

        $stats = $response->viewData('page')['props']['stats']['admin'] ?? [];

        foreach (['pending_orders', 'overdue_invoices', 'open_support_tickets', 'tickets_waiting'] as $key) {
            $this->assertArrayHasKey($key, $stats, "The header needs {$key} to render its chip.");
            $this->assertIsInt($stats[$key]);
        }
    }

    #[Test]
    public function the_dashboard_link_is_not_active_on_every_admin_page(): void
    {
        // The layout matched the bare '/admin' prefix, which every admin URL
        // starts with. Guard the shape the layout relies on.
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $response = $this->actingAs($admin)->get(route('admin.customers.index'));
        $response->assertOk();

        $url = $response->viewData('page')['url'] ?? '';

        $this->assertStringStartsWith('/admin/customers', $url);
        $this->assertNotSame('/admin', $url);
    }

    #[Test]
    public function a_transaction_fee_is_recorded_against_the_payment(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create(['name' => 'Fee Client', 'status' => 'active']);

        // A migration/seeder already provisions the standard gateways.
        $gateway = PaymentGateway::firstOrCreate(
            ['slug' => 'test-manual'],
            ['name' => 'Test Manual', 'driver' => 'manual', 'is_active' => true]
        );

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'FEE-1',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 1000,
            'late_fee' => 0,
            'total' => 1000,
            'currency' => 'BDT',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.invoices.add-payment', $invoice), [
                'entry_date' => now()->toDateString(),
                'amount' => '1000',
                'payment_gateway_id' => $gateway->id,
                'reference' => 'TRX123',
                'transaction_fee' => '18.50',
            ])
            ->assertRedirect(route('admin.invoices.show', $invoice));

        $entry = AccountingEntry::where('invoice_id', $invoice->id)
            ->where('type', 'payment')
            ->firstOrFail();

        $this->assertSame(18.5, (float) ($entry->metadata['transaction_fee'] ?? 0));
        $this->assertSame(981.5, (float) ($entry->metadata['net_amount'] ?? 0));

        // The fee does not reduce what the customer settled.
        $this->assertSame('paid', $invoice->fresh()->status);

        // The paid transition must still be audited — the shared completion
        // handler owns that flip now, so this guards against it being skipped
        // because a caller flipped the status first.
        $this->assertDatabaseHas('status_audit_logs', [
            'model_type' => Invoice::class,
            'model_id' => $invoice->id,
            'new_status' => 'paid',
            'reason' => 'invoice_add_payment',
        ]);
    }

    #[Test]
    public function marking_an_invoice_paid_by_hand_still_records_a_payment(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create(['name' => 'Manual Client', 'status' => 'active']);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'MANUAL-1',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 250,
            'late_fee' => 0,
            'total' => 250,
            'currency' => 'BDT',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.invoices.mark-paid', $invoice))
            ->assertRedirect();

        $this->assertSame('paid', $invoice->fresh()->status);

        $paid = (float) AccountingEntry::where('invoice_id', $invoice->id)
            ->where('type', 'payment')
            ->sum('amount');

        $this->assertSame(
            250.0,
            $paid,
            'A "paid" invoice with no payment entry keeps showing as owing money everywhere else.'
        );
    }
}
