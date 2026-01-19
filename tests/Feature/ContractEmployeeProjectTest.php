<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContractEmployeeProjectTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function contract_employee_amount_is_required_on_create(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create(['name' => 'Contract Client']);
        $employee = Employee::create([
            'name' => 'Contractor One',
            'employment_type' => 'contract',
            'status' => 'active',
        ]);

        $payload = $this->projectPayload($customer->id, [$employee->id], 'employee:' . $employee->id);

        $response = $this->actingAs($admin)->post(route('admin.projects.store'), $payload);

        $response->assertSessionHasErrors('contract_employee_amounts.' . $employee->id);
    }

    #[Test]
    public function non_contract_employee_does_not_set_contract_fields(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create(['name' => 'Standard Client']);
        $employee = Employee::create([
            'name' => 'Full Time',
            'employment_type' => 'full_time',
            'status' => 'active',
        ]);

        $payload = $this->projectPayload($customer->id, [$employee->id], 'employee:' . $employee->id);

        $this->actingAs($admin)->post(route('admin.projects.store'), $payload)
            ->assertSessionHasNoErrors();

        $project = Project::query()->where('name', $payload['name'])->firstOrFail();

        $this->assertNull($project->contract_amount);
        $this->assertNull($project->contract_employee_total_earned);
        $this->assertNull($project->contract_employee_payable);
        $this->assertNull($project->contract_employee_payout_status);
    }

    #[Test]
    public function contract_employee_payable_updates_on_project_completion(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create(['name' => 'Completion Client']);
        $employee = Employee::create([
            'name' => 'Contractor Two',
            'employment_type' => 'contract',
            'status' => 'active',
        ]);

        $payload = $this->projectPayload($customer->id, [$employee->id], 'employee:' . $employee->id, [
            'contract_employee_amounts' => [
                $employee->id => 250,
            ],
        ]);

        $this->actingAs($admin)->post(route('admin.projects.store'), $payload)
            ->assertSessionHasNoErrors();

        $project = Project::query()->where('name', $payload['name'])->firstOrFail();

        $this->assertSame(250.0, (float) $project->contract_employee_total_earned);
        $this->assertSame(0.0, (float) $project->contract_employee_payable);
        $this->assertSame('earned', $project->contract_employee_payout_status);

        $updatePayload = [
            'name' => $project->name,
            'customer_id' => $project->customer_id,
            'type' => $project->type,
            'status' => 'complete',
            'total_budget' => $project->total_budget,
            'initial_payment_amount' => $project->initial_payment_amount,
            'currency' => $project->currency,
            'employee_ids' => [$employee->id],
            'sales_rep_ids' => [],
            'contract_employee_amounts' => [
                $employee->id => 250,
            ],
        ];

        $this->actingAs($admin)->patch(route('admin.projects.update', $project), $updatePayload)
            ->assertSessionHasNoErrors();

        $project->refresh();

        $this->assertSame(250.0, (float) $project->contract_employee_payable);
        $this->assertSame('payable', $project->contract_employee_payout_status);

        $this->actingAs($admin)->patch(route('admin.projects.update', $project), $updatePayload)
            ->assertSessionHasNoErrors();

        $project->refresh();

        $this->assertSame(250.0, (float) $project->contract_employee_payable);
        $this->assertSame('payable', $project->contract_employee_payout_status);
    }

    #[Test]
    public function contract_employee_amount_is_required_on_edit(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create(['name' => 'Edit Client']);
        $employee = Employee::create([
            'name' => 'Contractor Three',
            'employment_type' => 'contract',
            'status' => 'active',
        ]);

        $project = Project::create([
            'name' => 'Edit Project',
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
            'status' => $project->status,
            'total_budget' => $project->total_budget,
            'initial_payment_amount' => $project->initial_payment_amount,
            'currency' => $project->currency,
            'employee_ids' => [$employee->id],
            'sales_rep_ids' => [],
        ];

        $this->actingAs($admin)
            ->patch(route('admin.projects.update', $project), $payload)
            ->assertSessionHasErrors('contract_employee_amounts.' . $employee->id);
    }

    #[Test]
    public function edit_updates_contract_fields_and_clears_when_removed(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create(['name' => 'Edit Switch Client']);
        $contractEmployee = Employee::create([
            'name' => 'Contractor Four',
            'employment_type' => 'contract',
            'status' => 'active',
        ]);
        $fullTimeEmployee = Employee::create([
            'name' => 'Full Time Two',
            'employment_type' => 'full_time',
            'status' => 'active',
        ]);

        $project = Project::create([
            'name' => 'Edit Switch Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        $updatePayload = [
            'name' => $project->name,
            'customer_id' => $project->customer_id,
            'type' => $project->type,
            'status' => $project->status,
            'total_budget' => $project->total_budget,
            'initial_payment_amount' => $project->initial_payment_amount,
            'currency' => $project->currency,
            'employee_ids' => [$contractEmployee->id],
            'sales_rep_ids' => [],
            'contract_employee_amounts' => [
                $contractEmployee->id => 300,
            ],
        ];

        $this->actingAs($admin)
            ->patch(route('admin.projects.update', $project), $updatePayload)
            ->assertSessionHasNoErrors();

        $project->refresh();

        $this->assertSame(300.0, (float) $project->contract_amount);
        $this->assertSame(300.0, (float) $project->contract_employee_total_earned);
        $this->assertSame(0.0, (float) $project->contract_employee_payable);

        $removePayload = [
            'name' => $project->name,
            'customer_id' => $project->customer_id,
            'type' => $project->type,
            'status' => $project->status,
            'total_budget' => $project->total_budget,
            'initial_payment_amount' => $project->initial_payment_amount,
            'currency' => $project->currency,
            'employee_ids' => [$fullTimeEmployee->id],
            'sales_rep_ids' => [],
        ];

        $this->actingAs($admin)
            ->patch(route('admin.projects.update', $project), $removePayload)
            ->assertSessionHasNoErrors();

        $project->refresh();

        $this->assertNull($project->contract_amount);
        $this->assertNull($project->contract_employee_total_earned);
        $this->assertNull($project->contract_employee_payable);
        $this->assertNull($project->contract_employee_payout_status);
    }

    private function projectPayload(int $customerId, array $employeeIds, string $assignee, array $overrides = []): array
    {
        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        $payload = [
            'name' => 'Contract Project',
            'customer_id' => $customerId,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
            'employee_ids' => $employeeIds,
            'sales_rep_ids' => [],
            'tasks' => [
                [
                    'title' => 'Task A',
                    'descriptions' => [''],
                    'task_type' => 'feature',
                    'priority' => 'medium',
                    'start_date' => $today,
                    'due_date' => $tomorrow,
                    'assignee' => $assignee,
                    'customer_visible' => 0,
                ],
            ],
        ];

        return array_replace_recursive($payload, $overrides);
    }
}
