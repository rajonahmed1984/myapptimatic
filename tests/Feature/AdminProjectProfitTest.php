<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\SalesRepresentative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminProjectProfitTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function profit_and_remaining_budget_deduct_sales_rep_and_contract_payouts(): void
    {
        $customer = Customer::create([
            'name' => 'Profit Customer',
        ]);

        $project = Project::create([
            'name' => 'Profit Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 20000,
            'initial_payment_amount' => 0,
            'currency' => 'BDT',
            'hourly_cost' => 0,
            'actual_hours' => 0,
            'contract_amount' => 0,
        ]);

        $salesUser = User::factory()->create();
        $salesRep = SalesRepresentative::create([
            'user_id' => $salesUser->id,
            'name' => 'Sales Rep',
            'email' => 'rep@example.com',
            'status' => 'active',
        ]);

        $project->salesRepresentatives()->attach($salesRep->id, [
            'amount' => 7000,
        ]);

        $admin = User::factory()->create([
            'role' => 'master_admin',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.projects.show', $project));

        $response->assertOk();
        $response->assertSee('Remaining budget: BDT 20,000.00');
        $response->assertSee('Profit: BDT 13,000.00');
    }

    #[Test]
    public function invoiceable_remaining_excludes_unpaid_invoices_and_ignores_vat_on_paid_ones(): void
    {
        $customer = Customer::create(['name' => 'Remaining Customer']);

        $project = Project::create([
            'name' => 'Remaining Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 200000,
            'initial_payment_amount' => 50000,
            'currency' => 'BDT',
            'hourly_cost' => 0,
            'actual_hours' => 0,
            'contract_amount' => 0,
        ]);

        // Unpaid initial payment invoice: still counts as remaining, but must
        // not be offered for invoicing a second time.
        Invoice::create([
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'number' => 'INV-R-1',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'subtotal' => 50000,
            'tax_amount' => 0,
            'late_fee' => 0,
            'total' => 50000,
            'currency' => 'BDT',
            'type' => 'project_initial_payment',
        ]);

        // Paid invoice with VAT: only the pre-VAT subtotal reduces the budget.
        Invoice::create([
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'number' => 'INV-R-2',
            'status' => 'paid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'subtotal' => 40000,
            'tax_amount' => 6000,
            'late_fee' => 0,
            'total' => 46000,
            'currency' => 'BDT',
            'type' => 'project_remaining_budget',
        ]);

        $admin = User::factory()->create(['role' => 'master_admin']);

        $response = $this->actingAs($admin)->get(route('admin.projects.show', $project));

        $response->assertOk();
        $response->assertSee('Remaining budget: BDT 160,000.00');
        $response->assertInertia(fn ($page) => $page->where('project.financials.remaining_budget_invoiceable_display', 'BDT 110,000.00'));

        $this->actingAs($admin)
            ->post(route('admin.projects.invoice-remaining', $project), ['amount' => 150000])
            ->assertSessionHasErrors('amount');
    }
}
