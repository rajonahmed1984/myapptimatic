<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\SalesRepresentative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SharedProjectViewsLegacyBridgeParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function client_task_detail_is_bridged_to_inertia_legacy_component(): void
    {
        [$customer, $project, $task] = $this->seedProjectWithTask();

        $client = User::factory()->create([
            'role' => 'client',
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($client)
            ->get(route('client.projects.tasks.show', [$project, $task]))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Projects\\/TaskDetailClickup', false);
    }

    #[Test]
    public function employee_task_detail_is_bridged_to_inertia_legacy_component(): void
    {
        [$customer, $project, $task] = $this->seedProjectWithTask();

        $employeeUser = User::factory()->create(['role' => 'employee']);
        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'name' => 'Employee Legacy Bridge',
            'status' => 'active',
        ]);
        $project->employees()->sync([$employee->id]);

        $task->update([
            'assigned_type' => 'employee',
            'assigned_id' => $employee->id,
        ]);

        $this->actingAs($employeeUser, 'employee')
            ->get(route('employee.projects.tasks.show', [$project, $task]))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Projects\\/TaskDetailClickup', false);
    }

    #[Test]
    public function sales_rep_task_detail_is_bridged_to_inertia_legacy_component(): void
    {
        [$customer, $project, $task] = $this->seedProjectWithTask();

        $repUser = User::factory()->create(['role' => 'sales']);
        $rep = SalesRepresentative::create([
            'user_id' => $repUser->id,
            'name' => 'Sales Legacy Bridge',
            'status' => 'active',
        ]);
        $project->salesRepresentatives()->sync([$rep->id]);

        $task->update([
            'assigned_type' => 'sales_rep',
            'assigned_id' => $rep->id,
        ]);

        $this->actingAs($repUser, 'sales')
            ->get(route('rep.projects.tasks.show', [$project, $task]))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Projects\\/TaskDetailClickup', false);
    }

    /**
     * @return array{Customer, Project, ProjectTask}
     */
    private function seedProjectWithTask(): array
    {
        $customer = Customer::create([
            'name' => 'Legacy Bridge Customer',
            'status' => 'active',
        ]);

        $project = Project::create([
            'name' => 'Legacy Bridge Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1200,
            'initial_payment_amount' => 200,
            'currency' => 'USD',
        ]);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Legacy Bridge Task',
            'status' => 'pending',
            'customer_visible' => true,
        ]);

        return [$customer, $project, $task];
    }
}
