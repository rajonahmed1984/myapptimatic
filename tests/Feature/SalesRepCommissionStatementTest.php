<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AccountingEntry;
use App\Models\CommissionAuditLog;
use App\Models\CommissionEarning;
use App\Models\CommissionPayout;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\SalesRepresentative;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\SalesRepStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The scenario the statement exists to make plain: a rep with a 10,000
 * commission on a 30,000 project whose client has paid 15,000 — all of it
 * collected and kept by the rep — plus a 1,000 company advance.
 */
class SalesRepCommissionStatementTest extends TestCase
{
    use RefreshDatabase;

    private SalesRepresentative $rep;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['role' => Role::SALES]);
        $this->rep = SalesRepresentative::create([
            'user_id' => $user->id,
            'name' => 'Osman',
            'email' => $user->email,
            'status' => 'active',
        ]);
        $this->customer = Customer::create(['name' => 'Client', 'status' => 'active']);
    }

    #[Test]
    public function project_commission_is_earned_in_proportion_to_client_payments(): void
    {
        $this->projectWithCommission(30000, 10000, clientPaid: 15000);

        $statement = app(SalesRepStatementService::class)->forRep($this->rep->id);

        $this->assertSame(10000.0, $statement['commission_total']);
        $this->assertSame(5000.0, $statement['commission_earned']);
        $this->assertSame(5000.0, $statement['waiting_on_clients']);
        $this->assertSame(50.0, $statement['lines'][0]['client_paid_percent']);
    }

    #[Test]
    public function an_invoice_marked_paid_without_a_payment_counts_as_client_paid(): void
    {
        $invoice = $this->invoice(['status' => 'paid', 'total' => 2000]);
        $this->earning(['source_type' => 'plan', 'invoice_id' => $invoice->id, 'commission_amount' => 200]);

        $statement = app(SalesRepStatementService::class)->forRep($this->rep->id);

        $this->assertSame(200.0, $statement['commission_earned']);
    }

    #[Test]
    public function kept_collections_and_company_advances_are_told_apart(): void
    {
        $project = $this->projectWithCommission(30000, 10000, clientPaid: 15000);
        $collected = Invoice::where('project_id', $project->id)->firstOrFail();
        $this->retainedCollection($collected, 15000);
        $this->companyAdvance($project, 1000);

        $statement = app(SalesRepStatementService::class)->forRep($this->rep->id);

        $this->assertSame(15000.0, $statement['taken']['retained']);
        $this->assertSame(1000.0, $statement['taken']['advances']);
        $this->assertSame(16000.0, $statement['taken']['net']);
        // Earned 5,000 against 16,000 received: holds 11,000 more than earned,
        // still 6,000 over even once the client pays the rest.
        $this->assertSame(-11000.0, $statement['balance']);
        $this->assertSame(-6000.0, $statement['balance_if_all_paid']);
        $this->assertSame(16000.0, $statement['lines'][0]['taken']);
    }

    #[Test]
    public function payable_is_limited_to_commission_clients_have_paid_for(): void
    {
        $project = $this->projectWithCommission(30000, 10000, clientPaid: 15000, status: 'complete');
        $earning = CommissionEarning::where('project_id', $project->id)->sole();

        $balance = app(CommissionService::class)->computeRepBalance($this->rep->id);
        $this->assertSame(5000.0, $balance['payable_balance']);

        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $this->actingAs($admin)
            ->post(route('admin.commission-payouts.store'), [
                'sales_rep_id' => $this->rep->id,
                'earning_ids' => [$earning->id],
            ])
            ->assertSessionHasErrors('payout');

        $this->assertSame(0, CommissionPayout::count());
    }

    #[Test]
    public function a_recovery_lowers_the_balance_and_the_sales_payout_expense(): void
    {
        $project = $this->projectWithCommission(30000, 10000, clientPaid: 15000);
        $this->retainedCollection(Invoice::where('project_id', $project->id)->firstOrFail(), 15000);

        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $this->actingAs($admin)
            ->post(route('admin.sales-reps.recovery', $this->rep), [
                'amount' => 4000,
                'reference' => 'CASH-1',
            ])
            ->assertSessionHas('status');

        $recovery = CommissionPayout::where('type', 'recovery')->sole();
        $this->assertSame('-4000.00', (string) $recovery->total_amount);

        $statement = app(SalesRepStatementService::class)->forRep($this->rep->id);
        $this->assertSame(4000.0, $statement['taken']['recovered']);
        $this->assertSame(11000.0, $statement['taken']['net']);
        $this->assertSame(-6000.0, $statement['balance']);

        $this->assertSame(11000.0, (float) CommissionPayout::where('status', 'paid')->sum('total_amount'), 'Expense sums net the recovery off.');
    }

    #[Test]
    public function a_recovery_cannot_exceed_what_the_rep_holds_over_earned(): void
    {
        $project = $this->projectWithCommission(30000, 10000, clientPaid: 15000);
        $this->retainedCollection(Invoice::where('project_id', $project->id)->firstOrFail(), 15000);

        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $this->actingAs($admin)
            ->post(route('admin.sales-reps.recovery', $this->rep), ['amount' => 10001])
            ->assertSessionHasErrors('recovery');

        $this->assertSame(0, CommissionPayout::where('type', 'recovery')->count());
    }

    #[Test]
    public function the_admin_and_rep_pages_carry_the_statement(): void
    {
        $project = $this->projectWithCommission(30000, 10000, clientPaid: 15000);
        $this->retainedCollection(Invoice::where('project_id', $project->id)->firstOrFail(), 15000);
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $this->actingAs($admin)->get(route('admin.sales-reps.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('reps.0.statement.balance', -10000));

        $this->actingAs($admin)->get(route('admin.sales-reps.show', $this->rep))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('statement.commission_earned', 5000)->where('statement.taken.retained', 15000));

        $this->actingAs($this->rep->user, 'sales')->get(route('rep.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('statement.balance', -10000));

        $this->actingAs($this->rep->user, 'sales')->get(route('rep.earnings.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('statement.lines', 1));
    }

    private function projectWithCommission(float $budget, float $commission, float $clientPaid, string $status = 'ongoing'): Project
    {
        $project = Project::create([
            'customer_id' => $this->customer->id,
            'name' => 'Website',
            'status' => $status,
            'total_budget' => $budget,
            'currency' => 'BDT',
        ]);

        $invoice = $this->invoice(['project_id' => $project->id, 'status' => 'unpaid', 'total' => $clientPaid]);
        if ($clientPaid > 0) {
            AccountingEntry::create([
                'entry_date' => '2026-10-01',
                'type' => 'payment',
                'amount' => $clientPaid,
                'currency' => 'BDT',
                'description' => 'Client payment',
                'customer_id' => $this->customer->id,
                'invoice_id' => $invoice->id,
            ]);
        }

        $this->earning([
            'source_type' => 'project',
            'source_id' => $project->id,
            'project_id' => $project->id,
            'commission_amount' => $commission,
            'paid_amount' => $budget,
            'status' => $status === 'complete' ? 'payable' : 'earned',
        ]);

        return $project;
    }

    private function invoice(array $attributes): Invoice
    {
        return Invoice::create(array_merge([
            'customer_id' => $this->customer->id,
            'number' => 'INV-'.uniqid(),
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-01',
            'subtotal' => $attributes['total'] ?? 0,
            'late_fee' => 0,
            'currency' => 'BDT',
        ], $attributes));
    }

    private function earning(array $attributes): CommissionEarning
    {
        return CommissionEarning::create(array_merge([
            'sales_representative_id' => $this->rep->id,
            'customer_id' => $this->customer->id,
            'currency' => 'BDT',
            'paid_amount' => 0,
            'status' => 'payable',
            'idempotency_key' => 'test:'.uniqid(),
        ], $attributes));
    }

    private function retainedCollection(Invoice $invoice, float $amount): void
    {
        $payout = CommissionPayout::create([
            'sales_representative_id' => $this->rep->id,
            'type' => 'advance',
            'total_amount' => $amount,
            'currency' => 'BDT',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        CommissionAuditLog::create([
            'sales_representative_id' => $this->rep->id,
            'commission_payout_id' => $payout->id,
            'action' => 'advance_payment',
            'status_to' => 'paid',
            'description' => 'Invoice collection retained by sales representative.',
            'metadata' => ['source_type' => 'invoice', 'source_id' => $invoice->id],
        ]);
    }

    private function companyAdvance(Project $project, float $amount): void
    {
        $payout = CommissionPayout::create([
            'sales_representative_id' => $this->rep->id,
            'type' => 'advance',
            'total_amount' => $amount,
            'currency' => 'BDT',
            'status' => 'paid',
            'paid_at' => now(),
            'project_id' => $project->id,
        ]);

        CommissionAuditLog::create([
            'sales_representative_id' => $this->rep->id,
            'commission_payout_id' => $payout->id,
            'action' => 'advance_payment',
            'status_to' => 'paid',
            'description' => 'Advance payment recorded.',
            'metadata' => ['source_type' => 'project', 'source_id' => $project->id],
        ]);
    }
}
