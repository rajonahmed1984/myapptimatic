<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AccountingEntry;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountingUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function accounting_index_is_inertia_when_legacy_flag_is_off(): void
    {
        config()->set('features.admin_accounting_index', false);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.accounting.index'));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Admin\\/Accounting\\/Index', false);
    }

    #[Test]
    public function accounting_index_remains_inertia_when_legacy_flag_is_on(): void
    {
        config()->set('features.admin_accounting_index', true);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.accounting.index'));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Admin\\/Accounting\\/Index', false);
    }

    #[Test]
    public function accounting_index_permission_guard_remains_forbidden_for_client_role_with_or_without_legacy_flag(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        config()->set('features.admin_accounting_index', false);
        $this->actingAs($client)
            ->get(route('admin.accounting.index'))
            ->assertForbidden();

        config()->set('features.admin_accounting_index', true);
        $this->actingAs($client)
            ->get(route('admin.accounting.index'))
            ->assertForbidden();

        $this->actingAs($client)
            ->getJson(route('admin.accounting.lookups.customers'))
            ->assertForbidden();
        $this->actingAs($client)
            ->getJson(route('admin.accounting.lookups.invoices'))
            ->assertForbidden();
    }

    #[Test]
    public function accounting_transactions_route_is_inertia_with_or_without_legacy_flag(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        config()->set('features.admin_accounting_index', false);
        $this->actingAs($admin)
            ->get(route('admin.accounting.transactions'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Accounting\\/Index', false);

        config()->set('features.admin_accounting_index', true);
        $this->actingAs($admin)
            ->get(route('admin.accounting.transactions'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Accounting\\/Index', false);
    }

    #[Test]
    public function accounting_index_paginates_on_the_server_without_losing_global_summaries_or_running_balances(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);
        $firstDate = Carbon::parse('2026-01-01');

        foreach (range(1, 35) as $day) {
            AccountingEntry::create([
                'entry_date' => $firstDate->copy()->addDays($day - 1),
                'type' => 'payment',
                'amount' => 10,
                'currency' => 'USD',
                'reference' => sprintf('phase-three-%03d', $day),
                'created_by' => $admin->id,
            ]);
        }

        $response = $this->actingAs($admin)->get(route('admin.accounting.index', [
            'search' => 'phase-three',
            'page' => 2,
        ]));

        $response->assertOk();
        $props = $this->inertiaProps($response->getContent());

        $this->assertCount(5, data_get($props, 'entries', []));
        $this->assertSame(35, data_get($props, 'summary.total_entries'));
        $this->assertSame(35, data_get($props, 'summary.inflow_entries'));
        $this->assertSame(0, data_get($props, 'summary.outflow_entries'));
        $this->assertSame(35, data_get($props, 'summary.currencies.0.entries_count'));
        $this->assertSame('USD 350.00', data_get($props, 'summary.currencies.0.net_display'));

        $this->assertSame(2, data_get($props, 'pagination.current_page'));
        $this->assertSame(2, data_get($props, 'pagination.last_page'));
        $this->assertSame(31, data_get($props, 'pagination.from'));
        $this->assertSame(35, data_get($props, 'pagination.to'));
        $this->assertStringContainsString('search=phase-three', (string) data_get($props, 'pagination.previous_url'));

        $this->assertSame('phase-three-005', data_get($props, 'entries.0.reference'));
        $this->assertSame('USD 50.00', data_get($props, 'entries.0.running_balance_display'));
        $this->assertSame('phase-three-001', data_get($props, 'entries.4.reference'));
        $this->assertSame('USD 10.00', data_get($props, 'entries.4.running_balance_display'));
    }

    #[Test]
    public function accounting_form_loads_customer_and_invoice_options_on_demand(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);
        $targetInvoice = null;

        foreach (range(1, 25) as $number) {
            $customer = Customer::query()->create([
                'name' => sprintf('Lookup Customer %03d', $number),
                'email' => sprintf('lookup-%03d@example.test', $number),
            ]);
            $invoice = Invoice::query()->create([
                'customer_id' => $customer->id,
                'number' => sprintf('LOOKUP-INV-%03d', $number),
                'status' => 'unpaid',
                'issue_date' => Carbon::parse('2026-01-01')->addDays($number),
                'due_date' => Carbon::parse('2026-02-01')->addDays($number),
                'subtotal' => 100,
                'total' => 100,
                'currency' => 'USD',
            ]);

            if ($number === 25) {
                $targetInvoice = $invoice;
            }
        }

        AccountingEntry::query()->create([
            'entry_date' => '2026-02-10',
            'type' => 'payment',
            'amount' => 40,
            'currency' => 'USD',
            'invoice_id' => $targetInvoice->id,
            'customer_id' => $targetInvoice->customer_id,
            'created_by' => $admin->id,
        ]);

        $form = $this->actingAs($admin)->get(route('admin.accounting.create'));
        $form->assertOk();
        $formProps = $this->inertiaProps($form->getContent());
        $this->assertSame([], data_get($formProps, 'customers'));
        $this->assertSame([], data_get($formProps, 'invoices'));

        $customers = $this->actingAs($admin)
            ->getJson(route('admin.accounting.lookups.customers'));
        $customers->assertOk()->assertJsonCount(20, 'data');

        $customerSearch = $this->actingAs($admin)->getJson(route('admin.accounting.lookups.customers', [
            'q' => 'Lookup Customer 025',
        ]));
        $customerSearch->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.label', 'Lookup Customer 025');

        $invoices = $this->actingAs($admin)
            ->getJson(route('admin.accounting.lookups.invoices'));
        $invoices->assertOk()->assertJsonCount(20, 'data');

        $invoiceSearch = $this->actingAs($admin)->getJson(route('admin.accounting.lookups.invoices', [
            'q' => 'LOOKUP-INV-025',
        ]));
        $invoiceSearch->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.invoice_label', 'LOOKUP-INV-025')
            ->assertJsonPath('data.0.customer_name', 'Lookup Customer 025')
            ->assertJsonPath('data.0.due_amount', 60);

        $prefilled = $this->actingAs($admin)->get(route('admin.accounting.create', [
            'invoice_id' => $targetInvoice->id,
        ]));
        $prefilled->assertOk();
        $prefilledProps = $this->inertiaProps($prefilled->getContent());
        $this->assertCount(1, data_get($prefilledProps, 'customers', []));
        $this->assertCount(1, data_get($prefilledProps, 'invoices', []));
        $this->assertSame(
            $targetInvoice->id,
            data_get($prefilledProps, 'form.selected_invoice.id')
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
