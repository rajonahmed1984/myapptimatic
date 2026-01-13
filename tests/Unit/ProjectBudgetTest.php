<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Project;
use App\Models\SalesRepresentative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectBudgetTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function remaining_budget_is_total_minus_sales_rep_amounts(): void
    {
        $customer = Customer::create([
            'name' => 'Budget Client',
        ]);

        $project = Project::create([
            'name' => 'Budget Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        $repUserOne = User::factory()->create(['role' => 'sales']);
        $repUserTwo = User::factory()->create(['role' => 'sales']);

        $repOne = SalesRepresentative::create([
            'user_id' => $repUserOne->id,
            'name' => 'Rep One',
            'email' => 'rep1@example.com',
        ]);

        $repTwo = SalesRepresentative::create([
            'user_id' => $repUserTwo->id,
            'name' => 'Rep Two',
            'email' => 'rep2@example.com',
        ]);

        $project->salesRepresentatives()->sync([
            $repOne->id => ['amount' => 250],
            $repTwo->id => ['amount' => 150],
        ]);

        $project->load('salesRepresentatives');

        $this->assertSame(400.0, $project->sales_rep_total);
        $this->assertSame(600.0, $project->remaining_budget);
    }
}
