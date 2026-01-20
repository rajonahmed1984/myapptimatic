<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientInvoiceCheckoutTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function paid_invoice_checkout_is_blocked(): void
    {
        $customer = Customer::create(['name' => 'Checkout Client']);
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-3001',
            'status' => 'paid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 100,
            'late_fee' => 0,
            'total' => 100,
            'currency' => 'USD',
            'paid_at' => now(),
            'type' => 'project_initial_payment',
        ]);

        $response = $this->actingAs($client)
            ->post(route('client.invoices.checkout', $invoice));

        $response->assertRedirect(route('client.invoices.pay', $invoice));
        $response->assertSessionHas('status', 'This invoice is already paid.');
    }

    #[Test]
    public function unpaid_invoice_checkout_requires_gateway(): void
    {
        $customer = Customer::create(['name' => 'Checkout Client']);
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-3002',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 120,
            'late_fee' => 0,
            'total' => 120,
            'currency' => 'USD',
            'type' => 'project_initial_payment',
        ]);

        $response = $this->actingAs($client)
            ->post(route('client.invoices.checkout', $invoice), []);

        $response->assertSessionHasErrors('payment_gateway_id');
    }
}
