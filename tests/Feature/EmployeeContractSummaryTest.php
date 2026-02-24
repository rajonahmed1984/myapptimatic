<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeContractSummaryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function contract_employee_sees_contract_summary_on_dashboard(): void
    {
        $user = User::factory()->create();
        $employee = Employee::create([
            'user_id' => $user->id,
            'name' => 'Contract Employee',
            'employment_type' => 'contract',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'name' => 'Contract Client',
        ]);

        $project = Project::create([
            'name' => 'Contract Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
            'contract_amount' => 500,
            'contract_employee_total_earned' => 500,
            'contract_employee_payable' => 0,
            'contract_employee_payout_status' => 'earned',
        ]);

        $project->employees()->sync([$employee->id]);

        $response = $this->actingAs($user, 'employee')
            ->get(route('employee.dashboard'));

        $response->assertOk();
        $response->assertSee('Employee\\/Dashboard\\/Index', false);
        $response->assertSee('&quot;contract_summary&quot;:{&quot;total_earned&quot;:500,&quot;payable&quot;:0}', false);
    }

    #[Test]
    public function non_contract_employee_does_not_see_contract_summary(): void
    {
        $user = User::factory()->create();
        Employee::create([
            'user_id' => $user->id,
            'name' => 'Full Time Employee',
            'employment_type' => 'full_time',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user, 'employee')
            ->get(route('employee.dashboard'));

        $response->assertOk();
        $response->assertSee('Employee\\/Dashboard\\/Index', false);
        $response->assertSee('&quot;contract_summary&quot;:null', false);
    }
}
