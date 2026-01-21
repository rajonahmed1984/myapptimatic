<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectMaintenance;
use App\Models\Setting;
use App\Services\BillingService;
use App\Services\InvoiceTaxService;
use App\Services\MaintenanceBillingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MaintenanceBillingServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_generates_a_monthly_invoice_and_updates_billing_dates(): void
    {
        $this->seedSettings();
        Carbon::setTestNow(Carbon::parse('2026-01-01'));

        $maintenance = $this->createMaintenance([
            'billing_cycle' => 'monthly',
            'start_date' => '2026-01-01',
            'next_billing_date' => '2026-01-01',
        ]);

        $service = $this->makeService();
        $metrics = $service->generateInvoicesForDueMaintenances(Carbon::today());

        $this->assertSame(1, $metrics['generated']);
        $this->assertSame(0, $metrics['skipped']);

        $invoice = $maintenance->invoices()->first();
        $this->assertNotNull($invoice);
        $this->assertSame('project_maintenance', $invoice->type);
        $this->assertSame($maintenance->id, $invoice->maintenance_id);
        $this->assertSame('2026-01-01', $invoice->issue_date->toDateString());
        $this->assertSame('2026-01-11', $invoice->due_date->toDateString());
        $this->assertSame('USD', $invoice->currency);
        $this->assertSame(1, $invoice->items()->count());

        $maintenance->refresh();
        $this->assertSame('2026-02-01', $maintenance->next_billing_date->toDateString());
        $this->assertNotNull($maintenance->last_billed_at);
    }

    #[Test]
    public function it_calculates_next_billing_date_for_yearly_maintenance(): void
    {
        $this->seedSettings();
        Carbon::setTestNow(Carbon::parse('2026-01-15'));

        $maintenance = $this->createMaintenance([
            'billing_cycle' => 'yearly',
            'start_date' => '2026-01-15',
            'next_billing_date' => '2026-01-15',
        ]);

        $service = $this->makeService();
        $service->generateInvoicesForDueMaintenances(Carbon::today());

        $maintenance->refresh();
        $this->assertSame('2027-01-15', $maintenance->next_billing_date->toDateString());
    }

    #[Test]
    public function it_skips_duplicate_invoices_in_the_same_billing_cycle(): void
    {
        $this->seedSettings();
        Carbon::setTestNow(Carbon::parse('2026-01-20'));

        $maintenance = $this->createMaintenance([
            'billing_cycle' => 'monthly',
            'start_date' => '2026-01-01',
            'next_billing_date' => '2026-01-01',
        ]);

        Invoice::create([
            'customer_id' => $maintenance->customer_id,
            'project_id' => $maintenance->project_id,
            'maintenance_id' => $maintenance->id,
            'number' => '1',
            'status' => 'unpaid',
            'issue_date' => '2026-01-10',
            'due_date' => '2026-01-20',
            'subtotal' => 120,
            'late_fee' => 0,
            'total' => 120,
            'currency' => 'USD',
            'type' => 'project_maintenance',
        ]);

        $service = $this->makeService();
        $metrics = $service->generateInvoicesForDueMaintenances(Carbon::today());

        $this->assertSame(0, $metrics['generated']);
        $this->assertSame(1, $metrics['skipped']);
        $this->assertSame(1, $maintenance->invoices()->count());

        $maintenance->refresh();
        $this->assertSame('2026-02-01', $maintenance->next_billing_date->toDateString());
    }

    private function seedSettings(): void
    {
        Setting::setValue('invoice_due_days', 10);
        Setting::setValue('currency', 'USD');
    }

    private function makeService(): MaintenanceBillingService
    {
        $taxService = app(InvoiceTaxService::class);

        return new MaintenanceBillingService(new BillingService($taxService), $taxService);
    }

    private function createMaintenance(array $overrides = []): ProjectMaintenance
    {
        $customer = Customer::create([
            'name' => 'Maintenance Client',
        ]);

        $project = Project::create([
            'name' => 'Maintenance Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        return ProjectMaintenance::create(array_merge([
            'project_id' => $project->id,
            'customer_id' => $customer->id,
            'title' => 'Hosting',
            'amount' => 120,
            'currency' => 'USD',
            'billing_cycle' => 'monthly',
            'start_date' => '2026-01-01',
            'next_billing_date' => '2026-01-01',
            'status' => 'active',
            'auto_invoice' => true,
            'sales_rep_visible' => false,
        ], $overrides));
    }
}
