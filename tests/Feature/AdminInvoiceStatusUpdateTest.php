<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminInvoiceStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_mark_invoice_paid_and_sets_paid_at(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create(['name' => 'Billing Client']);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-4001',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 100,
            'late_fee' => 0,
            'total' => 100,
            'currency' => 'USD',
            'type' => 'project_initial_payment',
        ]);

        $payload = [
            'status' => 'paid',
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
        ];

        $this->actingAs($admin)
            ->put(route('admin.invoices.update', $invoice), $payload)
            ->assertRedirect(route('admin.invoices.show', $invoice));

        $invoice->refresh();

        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);
        $this->assertNull($invoice->overdue_at);
    }

    #[Test]
    public function admin_can_mark_invoice_overdue_and_sets_overdue_at(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create(['name' => 'Billing Client']);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-4002',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 120,
            'late_fee' => 0,
            'total' => 120,
            'currency' => 'USD',
            'type' => 'project_initial_payment',
        ]);

        $payload = [
            'status' => 'overdue',
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
        ];

        $this->actingAs($admin)
            ->put(route('admin.invoices.update', $invoice), $payload)
            ->assertRedirect(route('admin.invoices.show', $invoice));

        $invoice->refresh();

        $this->assertSame('overdue', $invoice->status);
        $this->assertNotNull($invoice->overdue_at);
        $this->assertNull($invoice->paid_at);
    }
}
