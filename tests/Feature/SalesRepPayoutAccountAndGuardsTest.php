<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AccountingEntry;
use App\Models\CommissionAuditLog;
use App\Models\CommissionEarning;
use App\Models\CommissionPayout;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Project;
use App\Models\SalesRepresentative;
use App\Models\User;
use App\Services\SalesRepPayoutRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SalesRepPayoutAccountAndGuardsTest extends TestCase
{
    use RefreshDatabase;

    private SalesRepresentative $rep;

    private Customer $customer;

    private User $admin;

    private string $method;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['role' => Role::SALES]);
        $this->rep = SalesRepresentative::create(['user_id' => $user->id, 'name' => 'Osman', 'email' => $user->email, 'status' => 'active']);
        $this->customer = Customer::create(['name' => 'Client', 'status' => 'active']);
        $this->admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $this->method = PaymentMethod::allowedCommissionPayoutCodes()[0];
    }

    // ---- 1. payout account

    #[Test]
    public function the_rep_saves_a_payout_account_that_is_stored_encrypted(): void
    {
        $this->actingAs($this->rep->user, 'sales')
            ->put(route('rep.profile.payout-account'), [
                'payout_method' => $this->method,
                'account_number' => '01711000000',
                'account_name' => 'Osman Aziz',
            ])
            ->assertRedirect(route('rep.profile.edit'));

        $rep = $this->rep->fresh();
        $this->assertSame($this->method, $rep->payout_method_default);
        $this->assertSame('01711000000', $rep->payoutAccount()['account_number']);

        $raw = (string) DB::table('sales_representatives')->where('id', $rep->id)->value('payout_details_encrypted');
        $this->assertStringNotContainsString('01711000000', $raw, 'The account number must not be stored in plain text.');
    }

    #[Test]
    public function a_rep_without_a_payout_account_is_asked_to_add_one_before_requesting(): void
    {
        $this->paidCompletedProject(10000);

        $availability = app(SalesRepPayoutRequestService::class)->availability($this->rep);

        $this->assertSame(10000.0, $availability['available']);
        $this->assertFalse($availability['can_request']);
        $this->assertTrue($availability['needs_payout_account']);
    }

    #[Test]
    public function the_admin_sees_where_to_pay_with_the_method_preselected(): void
    {
        $this->saveAccount();
        $this->paidCompletedProject(10000);
        app(SalesRepPayoutRequestService::class)->submit($this->rep->fresh(), 5000);

        $methodName = PaymentMethod::commissionPayoutDropdownOptions()->firstWhere('code', $this->method)->name;

        $this->actingAs($this->admin)
            ->get(route('admin.payout-requests.index'))
            ->assertInertia(fn ($page) => $page
                ->where('requests.0.pay_to_method', $this->method)
                ->where('requests.0.pay_to', fn ($payTo) => str_contains((string) $payTo, '01711000000') && str_contains((string) $payTo, $methodName)));
    }

    // ---- 2. keeping collected money

    #[Test]
    public function a_rep_can_keep_only_the_commission_a_collection_earns(): void
    {
        [, $invoice] = $this->unpaidProject(budget: 30000, commission: 10000, invoiceTotal: 15000);

        // Collecting 15,000 covers half of the 30,000 budget, earning half of the 10,000 commission.
        $this->collect($invoice, 15000, retained: 15000)->assertSessionHasErrors('retained_amount');
        $this->assertSame(0, CommissionPayout::count());
        $this->assertSame('unpaid', $invoice->fresh()->status);

        $this->collect($invoice, 15000, retained: 5000)->assertSessionHasNoErrors();
        $this->assertSame('5000.00', (string) CommissionPayout::sole()->total_amount);
    }

    #[Test]
    public function keeping_more_needs_an_explicit_allow_and_is_recorded(): void
    {
        [, $invoice] = $this->unpaidProject(budget: 30000, commission: 10000, invoiceTotal: 15000);

        $this->collect($invoice, 15000, retained: 15000, allow: true)->assertSessionHasNoErrors();

        $payout = CommissionPayout::sole();
        $this->assertSame('15000.00', (string) $payout->total_amount);
        $meta = CommissionAuditLog::where('commission_payout_id', $payout->id)->value('metadata');
        $meta = is_array($meta) ? $meta : json_decode((string) $meta, true);
        $this->assertNotNull($meta);
        $entry = AccountingEntry::where('invoice_id', $invoice->id)->sole();
        $this->assertEquals(10000, $entry->metadata['over_retained'] ?? null);
    }

    #[Test]
    public function a_rep_who_already_holds_extra_can_keep_nothing(): void
    {
        [$project, $invoice] = $this->unpaidProject(budget: 30000, commission: 10000, invoiceTotal: 15000);
        // Already kept 20,000 from an earlier collection, earning nothing.
        $this->keptCollection($project, 20000);

        $limit = app(\App\Services\SalesRepStatementService::class)->retainLimit($this->rep->id, $invoice, 15000);

        $this->assertSame(-20000.0, $limit['balance']);
        $this->assertSame(5000.0, $limit['from_this_payment']);
        $this->assertSame(0.0, $limit['limit']);

        $this->collect($invoice, 15000, retained: 1)->assertSessionHasErrors('retained_amount');
    }

    // ---- 3. decline email

    #[Test]
    public function declining_a_request_emails_the_rep_with_the_reason(): void
    {
        config(['mail.default' => 'array']);
        $this->saveAccount();
        $this->paidCompletedProject(10000);
        $request = app(SalesRepPayoutRequestService::class)->submit($this->rep->fresh(), 5000);

        $this->actingAs($this->admin)
            ->post(route('admin.sales-reps.payout-requests.reject', [$this->rep, $request]), ['admin_note' => 'Wait for month end'])
            ->assertSessionHas('status');

        $messages = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
        $declined = collect($messages)->first(fn ($sent) => str_contains((string) $sent->getOriginalMessage()->getSubject(), 'declined'));

        $this->assertNotNull($declined, 'The rep should be emailed when a request is declined.');
        $this->assertStringContainsString('Wait for month end', (string) $declined->getOriginalMessage()->getHtmlBody());
    }

    private function saveAccount(): void
    {
        $this->rep->update([
            'payout_method_default' => $this->method,
            'payout_details_encrypted' => ['account_number' => '01711000000', 'account_name' => 'Osman Aziz'],
        ]);
    }

    private function collect(Invoice $invoice, float $collected, float $retained, bool $allow = false)
    {
        return $this->actingAs($this->admin)
            ->from(route('admin.invoices.show', $invoice))
            ->post(route('admin.invoices.collect-by-sales-rep', $invoice), array_filter([
                'sales_rep_id' => $this->rep->id,
                'collected_amount' => $collected,
                'retained_amount' => $retained,
                'payout_method' => $this->method,
                'allow_over_retain' => $allow ? '1' : null,
            ], fn ($value) => $value !== null));
    }

    /**
     * @return array{0: Project, 1: Invoice}
     */
    private function unpaidProject(float $budget, float $commission, float $invoiceTotal): array
    {
        $project = Project::create([
            'customer_id' => $this->customer->id,
            'name' => 'Website',
            'status' => 'ongoing',
            'total_budget' => $budget,
            'currency' => 'BDT',
        ]);
        DB::table('project_sales_representative')->insert([
            'project_id' => $project->id,
            'sales_representative_id' => $this->rep->id,
            'amount' => $commission,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        CommissionEarning::create([
            'sales_representative_id' => $this->rep->id,
            'source_type' => 'project',
            'source_id' => $project->id,
            'project_id' => $project->id,
            'customer_id' => $this->customer->id,
            'currency' => 'BDT',
            'paid_amount' => $budget,
            'commission_amount' => $commission,
            'status' => 'earned',
            'idempotency_key' => 'project:'.$project->id.':rep:'.$this->rep->id,
        ]);

        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'project_id' => $project->id,
            'number' => 'INV-'.uniqid(),
            'status' => 'unpaid',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-10',
            'subtotal' => $invoiceTotal,
            'late_fee' => 0,
            'total' => $invoiceTotal,
            'currency' => 'BDT',
        ]);

        return [$project, $invoice];
    }

    private function paidCompletedProject(float $commission): void
    {
        $project = Project::create([
            'customer_id' => $this->customer->id,
            'name' => 'Done project',
            'status' => 'complete',
            'total_budget' => $commission * 3,
            'currency' => 'BDT',
        ]);
        Invoice::create([
            'customer_id' => $this->customer->id,
            'project_id' => $project->id,
            'number' => 'INV-'.uniqid(),
            'status' => 'paid',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-01',
            'subtotal' => $commission * 3,
            'late_fee' => 0,
            'total' => $commission * 3,
            'currency' => 'BDT',
        ]);
        CommissionEarning::create([
            'sales_representative_id' => $this->rep->id,
            'source_type' => 'project',
            'source_id' => $project->id,
            'project_id' => $project->id,
            'customer_id' => $this->customer->id,
            'currency' => 'BDT',
            'paid_amount' => $commission * 3,
            'commission_amount' => $commission,
            'status' => 'payable',
            'payable_at' => now(),
            'idempotency_key' => 'project:'.$project->id.':rep:'.$this->rep->id,
        ]);
    }

    private function keptCollection(Project $project, float $amount): void
    {
        $other = Invoice::create([
            'customer_id' => $this->customer->id,
            'number' => 'INV-'.uniqid(),
            'status' => 'paid',
            'issue_date' => '2026-09-01',
            'due_date' => '2026-09-01',
            'subtotal' => $amount,
            'late_fee' => 0,
            'total' => $amount,
            'currency' => 'BDT',
        ]);
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
            'metadata' => ['source_type' => 'invoice', 'source_id' => $other->id],
        ]);
    }
}
