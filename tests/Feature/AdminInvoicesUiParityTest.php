<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminInvoicesUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_invoice_pages_render_direct_inertia_components(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $customer = Customer::create([
            'name' => 'Parity Invoice Customer',
            'email' => 'parity-invoice@example.test',
            'status' => 'active',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-9001',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 100,
            'late_fee' => 0,
            'total' => 100,
            'currency' => 'USD',
            'type' => 'manual',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.invoices.index'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Invoices\\/Index', false);

        $this->actingAs($admin)
            ->get(route('admin.invoices.create'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Invoices\\/Create', false);

        $this->actingAs($admin)
            ->get(route('admin.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Invoices\\/Show', false);

        $response = $this->actingAs($admin)
            ->get(route('admin.invoices.client-view', $invoice));

        $response
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Client\\/Invoices\\/Pay', false);

        $props = $this->inertiaProps($response->getContent());
        $this->assertSame(route('client.invoices.checkout', $invoice), data_get($props, 'routes.checkout'));
        $this->assertSame(route('admin.invoices.download', $invoice), data_get($props, 'routes.download'));
    }

    #[Test]
    public function admin_invoice_store_contract_is_preserved(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $customer = Customer::create([
            'name' => 'Store Invoice Customer',
            'email' => 'store-invoice@example.test',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.invoices.store'), [
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'notes' => 'Manual invoice from parity test',
            'items' => [
                [
                    'description' => 'Consulting Retainer',
                    'quantity' => 2,
                    'unit_price' => 50,
                ],
            ],
        ]);

        $createdInvoice = Invoice::query()->latest('id')->firstOrFail();

        $response
            ->assertRedirect(route('admin.invoices.show', $createdInvoice))
            ->assertSessionHas('status', 'Invoice created.');
    }

    #[Test]
    public function client_role_cannot_access_admin_invoice_routes(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        $customer = Customer::create([
            'name' => 'Blocked Client View Customer',
            'email' => 'blocked-client-view@example.test',
            'status' => 'active',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-9009',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 100,
            'late_fee' => 0,
            'total' => 100,
            'currency' => 'USD',
            'type' => 'manual',
        ]);

        $this->actingAs($client)
            ->get(route('admin.invoices.index'))
            ->assertForbidden();

        $this->actingAs($client)
            ->get(route('admin.invoices.client-view', $invoice))
            ->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function inertiaProps(string $html): array
    {
        preg_match('/data-page="([^"]+)"/', $html, $matches);
        $this->assertArrayHasKey(1, $matches, 'Inertia payload is missing in response.');

        $decoded = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        $payload = json_decode($decoded, true);
        $this->assertIsArray($payload);

        $props = data_get($payload, 'props', []);
        $this->assertIsArray($props);

        return $props;
    }
}
