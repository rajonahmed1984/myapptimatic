<?php

namespace Tests\Feature;

use App\Models\AccountingEntry;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * An invoice cancelled after part of it was collected used to be shown as
 * "Partially Paid" with the remainder still due.
 */
class CancelledInvoiceDisplayTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function customer_invoice_list_shows_a_part_paid_cancelled_invoice_as_cancelled(): void
    {
        [$admin, $customer, $cancelled, $open] = $this->setUpInvoices();

        $this->actingAs($admin)
            ->get(route('admin.customers.show', ['customer' => $customer, 'tab' => 'invoices']))
            ->assertOk()
            ->assertInertia(function ($page) use ($cancelled, $open) {
                $rows = collect($page->toArray()['props']['invoices'])->keyBy('id');

                $this->assertSame('cancelled', $rows[$cancelled->id]['status']);
                $this->assertSame('Cancelled', $rows[$cancelled->id]['status_label']);
                $this->assertFalse($rows[$cancelled->id]['is_partially_paid']);

                $this->assertSame('partially_paid', $rows[$open->id]['status']);
                $this->assertTrue($rows[$open->id]['is_partially_paid']);
            });
    }

    #[Test]
    public function invoice_list_does_not_flag_a_cancelled_invoice_as_partial(): void
    {
        [$admin, , $cancelled, $open] = $this->setUpInvoices();

        $this->actingAs($admin)
            ->get(route('admin.invoices.index'))
            ->assertOk()
            ->assertInertia(function ($page) use ($cancelled, $open) {
                $rows = collect($page->toArray()['props']['invoices'])->keyBy('id');

                $this->assertSame('cancelled', $rows[$cancelled->id]['status']);
                $this->assertFalse($rows[$cancelled->id]['is_partial']);
                $this->assertTrue($rows[$open->id]['is_partial']);
            });
    }

    #[Test]
    public function a_cancelled_invoice_has_no_balance_due(): void
    {
        [$admin, , $cancelled] = $this->setUpInvoices();

        $this->actingAs($admin)
            ->get(route('admin.invoices.show', $cancelled))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('invoice.totals.outstanding_value', '0.00'));
    }

    private function setUpInvoices(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::create(['name' => 'Cancelled Invoice Customer', 'status' => 'active']);

        $cancelled = $this->invoice($customer, 'INV-CANCELLED-1', 'cancelled', 30000, 8000);
        $open = $this->invoice($customer, 'INV-OPEN-1', 'unpaid', 70000, 16000);

        return [$admin, $customer, $cancelled, $open];
    }

    private function invoice(Customer $customer, string $number, string $status, float $total, float $paid): Invoice
    {
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'number' => $number,
            'status' => $status,
            'issue_date' => '2026-05-17',
            'due_date' => '2026-05-24',
            'subtotal' => $total,
            'late_fee' => 0,
            'total' => $total,
            'currency' => 'BDT',
        ]);

        AccountingEntry::create([
            'entry_date' => '2026-05-18',
            'type' => 'payment',
            'amount' => $paid,
            'currency' => 'BDT',
            'description' => 'Part payment',
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
        ]);

        return $invoice;
    }
}
