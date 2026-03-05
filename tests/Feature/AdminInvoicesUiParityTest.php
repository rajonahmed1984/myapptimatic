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

        $indexResponse = $this->actingAs($admin)
            ->get(route('admin.invoices.index'))
            ->assertOk();
        $this->assertContainsInertiaComponent($indexResponse->getContent(), 'Admin/Invoices/Index');

        $createResponse = $this->actingAs($admin)
            ->get(route('admin.invoices.create'))
            ->assertOk();
        $this->assertContainsInertiaComponent($createResponse->getContent(), 'Admin/Invoices/Create');

        $showResponse = $this->actingAs($admin)
            ->get(route('admin.invoices.show', $invoice))
            ->assertOk();
        $this->assertContainsInertiaComponent($showResponse->getContent(), 'Admin/Invoices/Show');

        $response = $this->actingAs($admin)
            ->get(route('admin.invoices.client-view', $invoice));

        $response->assertOk();
        $this->assertContainsInertiaComponent($response->getContent(), 'Client/Invoices/Pay');
        $this->assertContainsRawOrEscaped($response->getContent(), route('client.invoices.checkout', $invoice));
        $this->assertContainsRawOrEscaped($response->getContent(), route('admin.invoices.download', $invoice));
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

    private function assertContainsInertiaComponent(string $content, string $component): void
    {
        $escaped = str_replace('/', '\\/', $component);

        $this->assertTrue(
            str_contains($content, $component) || str_contains($content, $escaped),
            "Response did not contain Inertia component [{$component}] in escaped or unescaped form."
        );
    }

    private function assertContainsRawOrEscaped(string $content, string $value): void
    {
        $escaped = str_replace('/', '\\/', $value);

        $this->assertTrue(
            str_contains($content, $value) || str_contains($content, $escaped),
            "Response did not contain [{$value}] in raw or slash-escaped form."
        );
    }
}
