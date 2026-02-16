<?php

namespace Tests\Feature;

use App\Models\CommissionPayout;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectMaintenance;
use App\Models\SalesRepresentative;
use App\Services\SalesRepBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SalesRepBalanceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function earned_from_project_and_maintenance_assignments_sums_correctly(): void
    {
        $rep = $this->createRep();
        $customer = $this->createCustomer();
        $project = $this->createProject($customer, 'ongoing');
        $maintenance = $this->createMaintenance($project, $customer, 'active');

        $project->salesRepresentatives()->sync([$rep->id => ['amount' => 120.50]]);
        $maintenance->salesRepresentatives()->sync([$rep->id => ['amount' => 79.50]]);

        $service = app(SalesRepBalanceService::class);
        $this->assertEqualsWithDelta(200.00, $service->totalEarned($rep->id), 0.001);
    }

    #[Test]
    public function paid_includes_advance_and_regular_paid_payouts(): void
    {
        $rep = $this->createRep();

        CommissionPayout::create([
            'sales_representative_id' => $rep->id,
            'type' => 'advance',
            'total_amount' => 40,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        CommissionPayout::create([
            'sales_representative_id' => $rep->id,
            'type' => 'regular',
            'total_amount' => 25,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        CommissionPayout::create([
            'sales_representative_id' => $rep->id,
            'type' => 'regular',
            'total_amount' => 90,
            'currency' => 'USD',
            'status' => 'draft',
        ]);

        $service = app(SalesRepBalanceService::class);
        $this->assertEqualsWithDelta(65.00, $service->totalPaidInclAdvance($rep->id), 0.001);
    }

    #[Test]
    public function payable_net_is_earned_minus_paid_and_can_be_negative(): void
    {
        $rep = $this->createRep();
        $customer = $this->createCustomer();
        $project = $this->createProject($customer, 'ongoing');
        $project->salesRepresentatives()->sync([$rep->id => ['amount' => 50]]);

        CommissionPayout::create([
            'sales_representative_id' => $rep->id,
            'type' => 'advance',
            'total_amount' => 70,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $service = app(SalesRepBalanceService::class);
        $this->assertEqualsWithDelta(-20.00, $service->payableNet($rep->id), 0.001);
    }

    #[Test]
    public function cancelled_records_are_excluded_from_earned(): void
    {
        $rep = $this->createRep();
        $customer = $this->createCustomer();

        $activeProject = $this->createProject($customer, 'ongoing');
        $cancelledProject = $this->createProject($customer, 'cancel');

        $activeMaintenance = $this->createMaintenance($activeProject, $customer, 'active');
        $cancelledMaintenance = $this->createMaintenance($cancelledProject, $customer, 'cancelled');

        $activeProject->salesRepresentatives()->sync([$rep->id => ['amount' => 100]]);
        $cancelledProject->salesRepresentatives()->sync([$rep->id => ['amount' => 60]]);
        $activeMaintenance->salesRepresentatives()->sync([$rep->id => ['amount' => 30]]);
        $cancelledMaintenance->salesRepresentatives()->sync([$rep->id => ['amount' => 20]]);

        $service = app(SalesRepBalanceService::class);
        $this->assertEqualsWithDelta(130.00, $service->totalEarned($rep->id), 0.001);
    }

    #[Test]
    public function payout_reversal_reduces_paid_total(): void
    {
        $rep = $this->createRep();

        $payout = CommissionPayout::create([
            'sales_representative_id' => $rep->id,
            'type' => 'regular',
            'total_amount' => 90,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $service = app(SalesRepBalanceService::class);
        $this->assertEqualsWithDelta(90.00, $service->totalPaidInclAdvance($rep->id), 0.001);

        $payout->update([
            'status' => 'reversed',
            'reversed_at' => now(),
        ]);

        $this->assertEqualsWithDelta(0.00, $service->totalPaidInclAdvance($rep->id), 0.001);
    }

    private function createRep(): SalesRepresentative
    {
        return SalesRepresentative::create([
            'name' => 'Rep ' . now()->timestamp . rand(10, 99),
            'status' => 'active',
        ]);
    }

    private function createCustomer(): Customer
    {
        return Customer::create([
            'name' => 'Customer ' . now()->timestamp . rand(100, 999),
            'status' => 'active',
        ]);
    }

    private function createProject(Customer $customer, string $status): Project
    {
        return Project::create([
            'name' => 'Project ' . now()->timestamp . rand(100, 999),
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => $status,
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);
    }

    private function createMaintenance(Project $project, Customer $customer, string $status): ProjectMaintenance
    {
        return ProjectMaintenance::create([
            'project_id' => $project->id,
            'customer_id' => $customer->id,
            'title' => 'Maintenance ' . rand(100, 999),
            'amount' => 300,
            'currency' => 'USD',
            'billing_cycle' => 'monthly',
            'start_date' => now()->toDateString(),
            'next_billing_date' => now()->toDateString(),
            'status' => $status,
            'auto_invoice' => true,
        ]);
    }
}

