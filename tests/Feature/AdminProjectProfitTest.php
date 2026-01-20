<?php

namespace Tests\Feature;

use App\Models\Customer;
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
}
