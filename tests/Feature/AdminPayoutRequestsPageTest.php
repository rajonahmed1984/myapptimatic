<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AccountingEntry;
use App\Models\CommissionEarning;
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

class AdminPayoutRequestsPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
    }

    #[Test]
    public function waiting_requests_from_every_rep_are_listed_with_what_is_payable(): void
    {
        $osman = $this->repWithPayable('Osman', 10000);
        $rahim = $this->repWithPayable('Rahim', 3000);
        $service = app(SalesRepPayoutRequestService::class);
        $service->submit($osman, 6000);
        $service->submit($rahim, 3000);

        $this->actingAs($this->admin)
            ->get(route('admin.payout-requests.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/PayoutRequests/Index')
                ->has('requests', 2)
                ->where('waiting_total', 9000)
                ->where('tabs.0.count', 2)
                ->where('requests.0.rep_name', 'Osman')
                ->where('requests.0.payable_now', 6000));

        $this->actingAs($this->admin)
            ->get(route('admin.payout-requests.index', ['search' => 'Rahim']))
            ->assertInertia(fn ($page) => $page->has('requests', 1)->where('requests.0.rep_name', 'Rahim'));
    }

    #[Test]
    public function paying_from_the_page_returns_to_it_and_moves_the_request_to_paid(): void
    {
        $rep = $this->repWithPayable('Osman', 10000);
        $request = app(SalesRepPayoutRequestService::class)->submit($rep, 6000);

        $this->actingAs($this->admin)
            ->from(route('admin.payout-requests.index'))
            ->post(route('admin.sales-reps.payout-requests.approve', [$rep, $request]), [
                'amount' => 6000,
                'payout_method' => PaymentMethod::allowedCommissionPayoutCodes()[0],
            ])
            ->assertRedirect(route('admin.payout-requests.index'));

        $this->assertSame('paid', $request->fresh()->status);

        $this->actingAs($this->admin)
            ->get(route('admin.payout-requests.index', ['status' => 'paid']))
            ->assertInertia(fn ($page) => $page->has('requests', 1)->where('requests.0.status', 'paid'));

        $this->actingAs($this->admin)
            ->get(route('admin.payout-requests.index'))
            ->assertInertia(fn ($page) => $page->has('requests', 0));
    }

    #[Test]
    public function the_login_page_links_to_the_sales_rep_sign_up(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('routes.sales_rep_register', '/register?as=sales_rep'));
    }

    private function repWithPayable(string $name, float $commission): SalesRepresentative
    {
        $user = User::factory()->create(['role' => Role::SALES]);
        $rep = SalesRepresentative::create([
            'user_id' => $user->id,
            'name' => $name,
            'email' => $user->email,
            'status' => 'active',
            'payout_method_default' => PaymentMethod::allowedCommissionPayoutCodes()[0],
            'payout_details_encrypted' => ['account_number' => '01711000000', 'account_name' => $name],
        ]);
        $customer = Customer::create(['name' => $name.' Client', 'status' => 'active']);
        $project = Project::create([
            'customer_id' => $customer->id,
            'name' => $name.' Project',
            'status' => 'complete',
            'total_budget' => $commission * 3,
            'currency' => 'BDT',
        ]);
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
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
        AccountingEntry::create([
            'entry_date' => '2026-10-01',
            'type' => 'payment',
            'amount' => $commission * 3,
            'currency' => 'BDT',
            'description' => 'Client payment',
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
        ]);
        CommissionEarning::create([
            'sales_representative_id' => $rep->id,
            'source_type' => 'project',
            'source_id' => $project->id,
            'project_id' => $project->id,
            'customer_id' => $customer->id,
            'currency' => 'BDT',
            'paid_amount' => $commission * 3,
            'commission_amount' => $commission,
            'status' => 'payable',
            'payable_at' => now(),
            'idempotency_key' => 'project:'.$project->id.':rep:'.$rep->id,
        ]);

        return $rep;
    }
}
