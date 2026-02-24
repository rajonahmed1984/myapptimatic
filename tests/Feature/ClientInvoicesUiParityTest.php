<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentAttempt;
use App\Models\PaymentGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientInvoicesUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function client_invoice_list_routes_render_inertia_pages(): void
    {
        $customer = Customer::create(['name' => 'Invoice Client']);
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $routes = [
            'client.invoices.index',
            'client.invoices.paid',
            'client.invoices.unpaid',
            'client.invoices.overdue',
            'client.invoices.cancelled',
            'client.invoices.refunded',
        ];

        foreach ($routes as $routeName) {
            $response = $this->actingAs($client)->get(route($routeName));

            $response->assertOk();
            $response->assertSee('data-page=');
            $response->assertSee('Client\\/Invoices\\/Index', false);
        }
    }

    #[Test]
    public function client_invoice_pay_route_renders_inertia_page_for_owner(): void
    {
        $customer = Customer::create(['name' => 'Invoice Client']);
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-9101',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 100,
            'late_fee' => 0,
            'total' => 100,
            'currency' => 'USD',
            'type' => 'project_initial_payment',
        ]);

        $response = $this->actingAs($client)->get(route('client.invoices.pay', $invoice));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Client\\/Invoices\\/Pay', false);
    }

    #[Test]
    public function client_manual_payment_route_renders_inertia_page_for_owner(): void
    {
        $customer = Customer::create(['name' => 'Invoice Client']);
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-9102',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 120,
            'late_fee' => 0,
            'total' => 120,
            'currency' => 'USD',
            'type' => 'project_initial_payment',
        ]);

        $gateway = PaymentGateway::query()->firstOrCreate(
            ['slug' => 'manual'],
            [
                'name' => 'Manual / Bank Transfer',
                'driver' => 'manual',
                'is_active' => true,
                'sort_order' => 1,
                'settings' => [
                    'instructions' => '',
                    'account_name' => '',
                    'account_number' => '',
                    'bank_name' => '',
                    'branch' => '',
                    'routing_number' => '',
                ],
            ]
        );

        $attempt = PaymentAttempt::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'payment_gateway_id' => $gateway->id,
            'status' => 'manual',
            'amount' => 120,
            'currency' => 'USD',
            'gateway_reference' => null,
            'external_id' => null,
            'payload' => null,
            'response' => null,
            'processed_at' => null,
        ]);

        $response = $this->actingAs($client)->get(route('client.invoices.manual', [$invoice, $attempt]));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Client\\/Invoices\\/Manual', false);
    }
}
