<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientInvoiceAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function client_can_view_own_invoice(): void
    {
        $customer = Customer::create(['name' => 'Invoice Client']);
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-1001',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 100,
            'late_fee' => 0,
            'total' => 100,
            'currency' => 'USD',
            'type' => 'project_initial_payment',
        ]);

        $response = $this->actingAs($client)
            ->get(route('client.invoices.pay', $invoice));

        $response->assertOk();
        $response->assertSee('Invoice #' . $invoice->id);
    }

    #[Test]
    public function client_cannot_view_other_customers_invoice(): void
    {
        $customer = Customer::create(['name' => 'Invoice Client']);
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $otherCustomer = Customer::create(['name' => 'Other Client']);
        $otherInvoice = Invoice::create([
            'customer_id' => $otherCustomer->id,
            'number' => 'INV-2001',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 200,
            'late_fee' => 0,
            'total' => 200,
            'currency' => 'USD',
            'type' => 'project_initial_payment',
        ]);

        $response = $this->actingAs($client)
            ->get(route('client.invoices.pay', $otherInvoice));

        $response->assertStatus(403);
    }
}
