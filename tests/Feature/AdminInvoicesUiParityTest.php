<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AccountingEntry;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
    public function invoice_details_print_data_and_pdf_deduct_paid_amount_from_total_due(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $customer = Customer::create([
            'name' => 'Partial Payment Customer',
            'email' => 'partial-payment@example.test',
            'status' => 'active',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-PARTIAL',
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
            'invoice_id' => $invoice->id,
            'description' => 'Partial payment service',
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => 100,
        ]);

        AccountingEntry::create([
            'entry_date' => now()->toDateString(),
            'type' => 'payment',
            'amount' => 40,
            'currency' => 'USD',
            'description' => 'Partial payment',
            'reference' => 'PAY-40',
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.invoices.show', $invoice))
            ->assertOk();

        $props = $this->inertiaProps($response->getContent());
        $this->assertSame('- USD 40.00', data_get($props, 'invoice.totals.paid_display'));
        $this->assertSame('USD 60.00', data_get($props, 'invoice.totals.outstanding_display'));

        $invoice->load(['items', 'customer', 'accountingEntries.paymentGateway']);
        $pdfHtml = view('client.invoices.pdf', [
            'invoice' => $invoice,
            'payToText' => 'Billing Department',
            'companyEmail' => 'billing@example.test',
        ])->render();

        $this->assertStringContainsString('<strong>Paid Amount</strong>', $pdfHtml);
        $this->assertStringContainsString('- USD 40.00', $pdfHtml);
        $this->assertStringContainsString('<strong>Total Due</strong>', $pdfHtml);
        $this->assertStringContainsString('USD 60.00', $pdfHtml);
    }

    #[Test]
    public function invoice_list_insights_use_all_filtered_rows_without_materializing_the_full_invoice_collection(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);
        $customer = Customer::create([
            'name' => 'Phase Four Customer',
            'email' => 'phase-four@example.test',
            'status' => 'active',
        ]);

        $createInvoice = function (string $number, string $status, float $total = 100) use ($customer): Invoice {
            return Invoice::create([
                'customer_id' => $customer->id,
                'number' => $number,
                'status' => $status,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->subDay()->toDateString(),
                'subtotal' => $total,
                'late_fee' => 0,
                'total' => $total,
                'currency' => 'USD',
                'type' => 'manual',
            ]);
        };

        foreach (range(1, 29) as $number) {
            $createInvoice(sprintf('PHASE4-UNPAID-%02d', $number), 'unpaid');
        }

        $partialOverdue = $createInvoice('PHASE4-PARTIAL', 'overdue');
        $effectivelyPaid = $createInvoice('PHASE4-EFFECTIVE-PAID', 'overdue');
        $createInvoice('PHASE4-CANCELLED', 'cancelled', 50);

        foreach ([[$partialOverdue, 40], [$effectivelyPaid, 100]] as [$invoice, $amount]) {
            AccountingEntry::create([
                'entry_date' => now()->toDateString(),
                'type' => 'payment',
                'amount' => $amount,
                'currency' => 'USD',
                'reference' => 'PHASE4-PAYMENT-'.$invoice->id,
                'customer_id' => $customer->id,
                'invoice_id' => $invoice->id,
                'created_by' => $admin->id,
            ]);
        }

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $response = $this->actingAs($admin)
            ->get(route('admin.invoices.index', ['search' => 'PHASE4']))
            ->assertOk();
        $props = $this->inertiaProps($response->getContent());

        $this->assertCount(30, data_get($props, 'invoices', []));
        $this->assertSame(32, data_get($props, 'pagination.total'));
        $this->assertSame(32, data_get($props, 'invoiceInsights.overview.count'));
        $this->assertSame('USD 3,150.00', data_get($props, 'invoiceInsights.overview.billed_display'));
        $this->assertSame('USD 140.00', data_get($props, 'invoiceInsights.overview.collected_display'));
        $this->assertSame('USD 3,010.00', data_get($props, 'invoiceInsights.overview.outstanding_display'));
        $this->assertSame(1, data_get($props, 'invoiceInsights.statuses.paid'));
        $this->assertSame(29, data_get($props, 'invoiceInsights.statuses.unpaid'));
        $this->assertSame(1, data_get($props, 'invoiceInsights.statuses.overdue'));
        $this->assertSame(1, data_get($props, 'invoiceInsights.statuses.cancelled'));
        $this->assertSame(1, data_get($props, 'invoiceInsights.statuses.partial'));
        $this->assertSame('USD 60.00', data_get($props, 'invoiceInsights.watchlist.overdue_amount_display'));

        $unpaidProps = $this->inertiaProps(
            $this->actingAs($admin)
                ->get(route('admin.invoices.unpaid', ['search' => 'PHASE4']))
                ->assertOk()
                ->getContent()
        );
        $this->assertSame(30, data_get($unpaidProps, 'invoiceInsights.overview.count'));
        $this->assertSame(29, data_get($unpaidProps, 'invoiceInsights.statuses.unpaid'));
        $this->assertSame(1, data_get($unpaidProps, 'invoiceInsights.statuses.overdue'));

        $paidProps = $this->inertiaProps(
            $this->actingAs($admin)
                ->get(route('admin.invoices.paid', ['search' => 'PHASE4']))
                ->assertOk()
                ->getContent()
        );
        $this->assertSame(1, data_get($paidProps, 'invoiceInsights.overview.count'));
        $this->assertSame(1, data_get($paidProps, 'invoiceInsights.statuses.paid'));

        $hasUnboundedInvoiceHydration = collect($queries)->contains(function (string $sql): bool {
            return (bool) preg_match('/select\s+(?:"invoices"\.)?\*\s+from\s+["`]?invoices["`]?/i', $sql)
                && ! str_contains($sql, ' limit ');
        });
        $hasAccountingEntryEagerLoad = collect($queries)->contains(function (string $sql): bool {
            return str_contains($sql, 'from "accounting_entries"')
                && str_contains($sql, '"accounting_entries"."invoice_id" in (');
        });
        $hasPaymentProofEagerLoad = collect($queries)->contains(function (string $sql): bool {
            return str_contains($sql, 'from "payment_proofs"')
                && str_contains($sql, '"payment_proofs"."invoice_id" in (');
        });

        $this->assertFalse($hasUnboundedInvoiceHydration, 'Invoice insights must not hydrate every filtered invoice.');
        $this->assertFalse($hasAccountingEntryEagerLoad, 'Invoice rows must use payment aggregates instead of eager loading entries.');
        $this->assertFalse($hasPaymentProofEagerLoad, 'Invoice rows must use proof flags instead of eager loading proofs.');
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

    /**
     * @return array<string, mixed>
     */
    private function inertiaProps(string $html): array
    {
        preg_match('/data-page="([^"]+)"/', $html, $matches);
        $this->assertArrayHasKey(1, $matches, 'Inertia payload is missing in response.');

        $payload = json_decode(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'), true);
        $this->assertIsArray($payload);

        $props = data_get($payload, 'props', []);
        $this->assertIsArray($props);

        return $props;
    }
}
