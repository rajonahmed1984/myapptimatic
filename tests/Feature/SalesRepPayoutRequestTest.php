<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AccountingEntry;
use App\Models\CommissionAuditLog;
use App\Models\CommissionEarning;
use App\Models\CommissionPayout;
use App\Models\CommissionPayoutRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Project;
use App\Models\SalesRepresentative;
use App\Models\User;
use App\Services\SalesRepPayoutRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SalesRepPayoutRequestTest extends TestCase
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
        $this->method = PaymentMethod::allowedCommissionPayoutCodes()[0];
        // Requests need somewhere to send the money.
        $this->rep = SalesRepresentative::create([
            'user_id' => $user->id,
            'name' => 'Rep',
            'email' => $user->email,
            'status' => 'active',
            'payout_method_default' => $this->method,
            'payout_details_encrypted' => ['account_number' => '01711000000', 'account_name' => 'Rep'],
        ]);
        $this->customer = Customer::create(['name' => 'Client', 'status' => 'active']);
        $this->admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
    }

    #[Test]
    public function the_rep_sees_what_is_available_and_can_request_it(): void
    {
        $this->paidCompletedProject(commission: 10000);

        $this->actingAs($this->rep->user, 'sales')
            ->get(route('rep.payouts.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('availability.available', 10000)
                ->where('availability.can_request', true));

        $this->actingAs($this->rep->user, 'sales')
            ->post(route('rep.payouts.requests.store'), ['amount' => 6000, 'note' => 'bKash please'])
            ->assertSessionHas('status');

        $request = CommissionPayoutRequest::sole();
        $this->assertSame('pending', $request->status);
        $this->assertSame('6000.00', (string) $request->amount);
    }

    #[Test]
    public function a_rep_cannot_ask_for_more_than_is_available_or_twice(): void
    {
        $this->paidCompletedProject(commission: 10000);

        $this->actingAs($this->rep->user, 'sales')
            ->post(route('rep.payouts.requests.store'), ['amount' => 10001])
            ->assertSessionHasErrors('amount');

        $this->actingAs($this->rep->user, 'sales')->post(route('rep.payouts.requests.store'), ['amount' => 3000]);
        $this->actingAs($this->rep->user, 'sales')
            ->post(route('rep.payouts.requests.store'), ['amount' => 3000])
            ->assertSessionHasErrors('amount');

        $this->assertSame(1, CommissionPayoutRequest::count());
    }

    #[Test]
    public function a_rep_holding_more_than_earned_sees_a_negative_amount_and_cannot_request(): void
    {
        $project = $this->paidCompletedProject(commission: 10000);
        $this->keptCollection(Invoice::where('project_id', $project->id)->firstOrFail(), 25000);

        $availability = app(SalesRepPayoutRequestService::class)->availability($this->rep);
        $this->assertSame(-15000.0, $availability['available']);
        $this->assertFalse($availability['can_request']);

        $this->actingAs($this->rep->user, 'sales')
            ->post(route('rep.payouts.requests.store'), ['amount' => 100])
            ->assertSessionHasErrors('amount');
    }

    #[Test]
    public function the_admin_pays_a_request_and_the_rep_sees_the_method(): void
    {
        $this->paidCompletedProject(commission: 10000);
        $request = app(SalesRepPayoutRequestService::class)->submit($this->rep, 6000);

        $this->actingAs($this->admin)
            ->get(route('admin.sales-reps.show', ['sales_rep' => $this->rep, 'tab' => 'payouts']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('payoutRequests.0.status', 'pending')->where('payoutRequests.0.payable_now', 6000));

        $this->actingAs($this->admin)
            ->post(route('admin.sales-reps.payout-requests.approve', [$this->rep, $request]), [
                'amount' => 6000,
                'payout_method' => $this->method,
                'reference' => 'TX-77',
            ])
            ->assertSessionHas('status');

        $request->refresh();
        $this->assertSame('paid', $request->status);
        $payout = CommissionPayout::findOrFail($request->commission_payout_id);
        $this->assertSame('paid', $payout->status);
        $this->assertSame('regular', $payout->type);
        $this->assertSame('6000.00', (string) $payout->total_amount);
        $this->assertSame($this->method, $payout->payout_method);

        $availability = app(SalesRepPayoutRequestService::class)->availability($this->rep);
        $this->assertSame(4000.0, $availability['available']);

        $methodName = PaymentMethod::commissionPayoutDropdownOptions()->firstWhere('code', $this->method)->name;
        $this->actingAs($this->rep->user, 'sales')
            ->get(route('rep.payouts.index'))
            ->assertInertia(fn ($page) => $page
                ->where('payout_requests.0.status', 'paid')
                ->where('payout_requests.0.method', $methodName)
                ->where('payout_requests.0.reference', 'TX-77'));
    }

    #[Test]
    public function paying_needs_a_method_and_cannot_exceed_what_is_payable(): void
    {
        $this->paidCompletedProject(commission: 10000);
        $request = app(SalesRepPayoutRequestService::class)->submit($this->rep, 6000);

        $this->actingAs($this->admin)
            ->post(route('admin.sales-reps.payout-requests.approve', [$this->rep, $request]), ['amount' => 6000])
            ->assertSessionHasErrors('payout_method');

        $this->actingAs($this->admin)
            ->post(route('admin.sales-reps.payout-requests.approve', [$this->rep, $request]), [
                'amount' => 7000,
                'payout_method' => $this->method,
            ])
            ->assertSessionHasErrors('payout_request');

        $this->assertSame('pending', $request->fresh()->status);
        $this->assertSame(0, CommissionPayout::count());
    }

    #[Test]
    public function the_admin_can_decline_and_the_rep_can_cancel(): void
    {
        $this->paidCompletedProject(commission: 10000);
        $service = app(SalesRepPayoutRequestService::class);

        $first = $service->submit($this->rep, 1000);
        $this->actingAs($this->admin)
            ->post(route('admin.sales-reps.payout-requests.reject', [$this->rep, $first]), ['admin_note' => 'Wait for month end'])
            ->assertSessionHas('status');
        $this->assertSame('rejected', $first->fresh()->status);
        $this->assertSame('Wait for month end', $first->fresh()->admin_note);

        $second = $service->submit($this->rep, 1000);
        $this->actingAs($this->rep->user, 'sales')
            ->post(route('rep.payouts.requests.cancel', $second))
            ->assertSessionHas('status');
        $this->assertSame('cancelled', $second->fresh()->status);
    }

    #[Test]
    public function a_rep_cannot_cancel_someone_elses_request(): void
    {
        $this->paidCompletedProject(commission: 10000);
        $request = app(SalesRepPayoutRequestService::class)->submit($this->rep, 1000);

        $otherUser = User::factory()->create(['role' => Role::SALES]);
        SalesRepresentative::create(['user_id' => $otherUser->id, 'name' => 'Other', 'email' => $otherUser->email, 'status' => 'active']);

        $this->actingAs($otherUser, 'sales')
            ->post(route('rep.payouts.requests.cancel', $request))
            ->assertNotFound();

        $this->assertSame('pending', $request->fresh()->status);
    }

    private function paidCompletedProject(float $commission): Project
    {
        $project = Project::create([
            'customer_id' => $this->customer->id,
            'name' => 'Website',
            'status' => 'complete',
            'total_budget' => 30000,
            'currency' => 'BDT',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'project_id' => $project->id,
            'number' => 'INV-'.uniqid(),
            'status' => 'paid',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-01',
            'subtotal' => 30000,
            'late_fee' => 0,
            'total' => 30000,
            'currency' => 'BDT',
        ]);

        AccountingEntry::create([
            'entry_date' => '2026-10-01',
            'type' => 'payment',
            'amount' => 30000,
            'currency' => 'BDT',
            'description' => 'Client payment',
            'customer_id' => $this->customer->id,
            'invoice_id' => $invoice->id,
        ]);

        CommissionEarning::create([
            'sales_representative_id' => $this->rep->id,
            'source_type' => 'project',
            'source_id' => $project->id,
            'project_id' => $project->id,
            'customer_id' => $this->customer->id,
            'currency' => 'BDT',
            'paid_amount' => 30000,
            'commission_amount' => $commission,
            'status' => 'payable',
            'payable_at' => now(),
            'idempotency_key' => 'project:'.$project->id.':rep:'.$this->rep->id,
        ]);

        return $project;
    }

    private function keptCollection(Invoice $invoice, float $amount): void
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
}
