<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_bulk_mark_invoices_paid(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create([
            'name' => 'Bulk Test Client',
            'email' => 'bulk_test@example.com',
        ]);

        $invoice1 = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-001',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 100,
            'late_fee' => 0,
            'total' => 100,
            'currency' => 'USD',
            'type' => 'manual',
        ]);

        $invoice2 = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-002',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 200,
            'late_fee' => 0,
            'total' => 200,
            'currency' => 'USD',
            'type' => 'manual',
        ]);

        $payload = [
            'invoice_ids' => [$invoice1->id, $invoice2->id],
        ];

        $response = $this->actingAs($admin)
            ->post(route('admin.invoices.bulk-mark-paid'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Marked 2 invoice(s) as paid successfully.');

        $invoice1->refresh();
        $invoice2->refresh();

        $this->assertSame('paid', $invoice1->status);
        $this->assertSame('paid', $invoice2->status);
        $this->assertNotNull($invoice1->paid_at);
        $this->assertNotNull($invoice2->paid_at);
    }

    #[Test]
    public function admin_can_bulk_mark_invoices_unpaid(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create([
            'name' => 'Bulk Test Client',
            'email' => 'bulk_test@example.com',
        ]);

        $invoice1 = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-001',
            'status' => 'paid',
            'paid_at' => now(),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 100,
            'late_fee' => 0,
            'total' => 100,
            'currency' => 'USD',
            'type' => 'manual',
        ]);

        $invoice2 = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-002',
            'status' => 'paid',
            'paid_at' => now(),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 200,
            'late_fee' => 0,
            'total' => 200,
            'currency' => 'USD',
            'type' => 'manual',
        ]);

        $payload = [
            'invoice_ids' => [$invoice1->id, $invoice2->id],
        ];

        $response = $this->actingAs($admin)
            ->post(route('admin.invoices.bulk-mark-unpaid'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Marked 2 invoice(s) as unpaid successfully.');

        $invoice1->refresh();
        $invoice2->refresh();

        $this->assertSame('unpaid', $invoice1->status);
        $this->assertSame('unpaid', $invoice2->status);
        $this->assertNull($invoice1->paid_at);
        $this->assertNull($invoice2->paid_at);
    }

    #[Test]
    public function admin_can_bulk_mark_invoices_cancelled(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create([
            'name' => 'Bulk Test Client',
            'email' => 'bulk_test@example.com',
        ]);

        $invoice1 = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-001',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 100,
            'late_fee' => 0,
            'total' => 100,
            'currency' => 'USD',
            'type' => 'manual',
        ]);

        $invoice2 = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-002',
            'status' => 'paid',
            'paid_at' => now(),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 200,
            'late_fee' => 0,
            'total' => 200,
            'currency' => 'USD',
            'type' => 'manual',
        ]);

        $payload = [
            'invoice_ids' => [$invoice1->id, $invoice2->id],
        ];

        $response = $this->actingAs($admin)
            ->post(route('admin.invoices.bulk-mark-cancelled'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Marked 2 invoice(s) as cancelled successfully.');

        $invoice1->refresh();
        $invoice2->refresh();

        $this->assertSame('cancelled', $invoice1->status);
        $this->assertSame('cancelled', $invoice2->status);
        $this->assertNull($invoice1->paid_at);
        $this->assertNull($invoice2->paid_at);
    }

    #[Test]
    public function admin_can_bulk_duplicate_invoices(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create([
            'name' => 'Bulk Test Client',
            'email' => 'bulk_test@example.com',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-001',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 100,
            'late_fee' => 0,
            'total' => 100,
            'currency' => 'USD',
            'type' => 'manual',
        ]);

        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Test Item 1',
            'quantity' => 2,
            'unit_price' => 50,
            'line_total' => 100,
        ]);

        $payload = [
            'invoice_ids' => [$invoice->id],
        ];

        $response = $this->actingAs($admin)
            ->post(route('admin.invoices.bulk-duplicate'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Duplicated 1 invoice(s) successfully.');

        // Verify a new invoice was created
        $newInvoice = Invoice::where('id', '!=', $invoice->id)->firstOrFail();
        $this->assertSame($customer->id, $newInvoice->customer_id);
        $this->assertSame('unpaid', $newInvoice->status);
        $this->assertEquals(now()->toDateString(), $newInvoice->issue_date->toDateString());
        $this->assertEquals(100, (float)$newInvoice->total);

        // Verify items were duplicated
        $this->assertCount(1, $newInvoice->items);
        $newItem = $newInvoice->items->first();
        $this->assertSame('Test Item 1', $newItem->description);
        $this->assertEquals(2, $newItem->quantity);
        $this->assertEquals(50, (float)$newItem->unit_price);
    }

    #[Test]
    public function admin_can_bulk_merge_invoices(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create([
            'name' => 'Bulk Test Client',
            'email' => 'bulk_test@example.com',
        ]);

        $invoice1 = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-001',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 100,
            'late_fee' => 0,
            'total' => 100,
            'currency' => 'USD',
            'type' => 'manual',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice1->id,
            'description' => 'Item A',
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => 100,
        ]);

        $invoice2 = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-002',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 200,
            'late_fee' => 0,
            'total' => 200,
            'currency' => 'USD',
            'type' => 'manual',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice2->id,
            'description' => 'Item B',
            'quantity' => 1,
            'unit_price' => 200,
            'line_total' => 200,
        ]);

        $payload = [
            'invoice_ids' => [$invoice1->id, $invoice2->id],
        ];

        $response = $this->actingAs($admin)
            ->post(route('admin.invoices.bulk-merge'), $payload);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Verify original invoices are cancelled
        $invoice1->refresh();
        $invoice2->refresh();
        $this->assertSame('cancelled', $invoice1->status);
        $this->assertSame('cancelled', $invoice2->status);

        // Verify new invoice is created with merged items
        $mergedInvoice = Invoice::where('notes', 'like', '%Merged from%')->firstOrFail();
        $this->assertSame($customer->id, $mergedInvoice->customer_id);
        $this->assertSame('unpaid', $mergedInvoice->status);
        $this->assertEquals(300, (float)$mergedInvoice->subtotal);

        // Verify new items are aggregated
        $this->assertCount(2, $mergedInvoice->items);
        $descriptions = $mergedInvoice->items->pluck('description')->toArray();
        $this->assertTrue(in_array('Item A (From invoice #INV-001)', $descriptions) || in_array('Item A (From invoice #' . $invoice1->id . ')', $descriptions));
        $this->assertTrue(in_array('Item B (From invoice #INV-002)', $descriptions) || in_array('Item B (From invoice #' . $invoice2->id . ')', $descriptions));
    }
}
