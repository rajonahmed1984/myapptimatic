<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CommissionEarning;
use App\Models\Customer;
use App\Models\Project;
use App\Models\SalesRepresentative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectCommissionPayableTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function commission_earnings_mark_payable_on_project_completion_idempotently(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create(['name' => 'Commission Client']);

        $salesUser = User::factory()->create(['role' => Role::SALES]);
        $salesRep = SalesRepresentative::create([
            'user_id' => $salesUser->id,
            'name' => 'Rep One',
            'status' => 'active',
        ]);

        $project = Project::create([
            'name' => 'Commission Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        $payload = [
            'name' => $project->name,
            'customer_id' => $project->customer_id,
            'type' => $project->type,
            'status' => 'complete',
            'total_budget' => $project->total_budget,
            'initial_payment_amount' => $project->initial_payment_amount,
            'currency' => $project->currency,
            'employee_ids' => [],
            'sales_rep_ids' => [$salesRep->id],
            'sales_rep_amounts' => [
                $salesRep->id => 150,
            ],
        ];

        $this->actingAs($admin)
            ->patch(route('admin.projects.update', $project), $payload)
            ->assertSessionHasNoErrors();

        $earning = CommissionEarning::query()->where('source_type', 'project')
            ->where('source_id', $project->id)
            ->first();

        $this->assertNotNull($earning);
        $this->assertSame('payable', $earning->status);
        $this->assertSame(150.0, (float) $earning->commission_amount);
        $this->assertSame(1, CommissionEarning::count());

        $this->actingAs($admin)
            ->patch(route('admin.projects.update', $project), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame(1, CommissionEarning::count());
        $this->assertSame('payable', CommissionEarning::first()->status);
    }
}
