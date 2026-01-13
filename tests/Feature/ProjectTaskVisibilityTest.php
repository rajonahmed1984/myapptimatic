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

class ProjectTaskVisibilityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function employee_sees_all_tasks_on_assigned_project(): void
    {
        [$project, $visibleTask, $hiddenTask, $employeeUser] = $this->setupProjectWithMembers();

        $response = $this->actingAs($employeeUser, 'employee')
            ->get(route('employee.projects.show', $project));

        $response->assertOk();
        $response->assertViewHas('tasks', function ($tasks) use ($visibleTask, $hiddenTask) {
            return $tasks->contains('id', $visibleTask->id)
                && $tasks->contains('id', $hiddenTask->id);
        });
    }

    #[Test]
    public function sales_rep_sees_all_tasks_on_assigned_project(): void
    {
        [$project, $visibleTask, $hiddenTask, $employeeUser, $salesUser] = $this->setupProjectWithMembers();

        $response = $this->actingAs($salesUser)
            ->get(route('rep.projects.show', $project));

        $response->assertOk();
        $response->assertViewHas('tasks', function ($tasks) use ($visibleTask, $hiddenTask) {
            return $tasks->contains('id', $visibleTask->id)
                && $tasks->contains('id', $hiddenTask->id);
        });
    }

    #[Test]
    public function client_sees_only_customer_visible_tasks(): void
    {
        [$project, $visibleTask, $hiddenTask, $employeeUser, $salesUser, $clientUser] = $this->setupProjectWithMembers();

        $response = $this->actingAs($clientUser)
            ->get(route('client.projects.show', $project));

        $response->assertOk();
        $response->assertViewHas('tasks', function ($tasks) use ($visibleTask, $hiddenTask) {
            return $tasks->contains('id', $visibleTask->id)
                && ! $tasks->contains('id', $hiddenTask->id);
        });
    }

    private function setupProjectWithMembers(): array
    {
        $customer = Customer::create([
            'name' => 'Visibility Client',
        ]);

        $project = Project::create([
            'name' => 'Visibility Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        $visibleTask = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Visible Task',
            'status' => 'pending',
            'customer_visible' => true,
        ]);

        $hiddenTask = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Hidden Task',
            'status' => 'pending',
            'customer_visible' => false,
        ]);

        $employeeUser = User::factory()->create();
        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'name' => 'Employee One',
            'status' => 'active',
        ]);
        $project->employees()->sync([$employee->id]);

        $salesUser = User::factory()->create(['role' => 'sales']);
        $salesRep = SalesRepresentative::create([
            'user_id' => $salesUser->id,
            'name' => 'Sales Rep',
            'status' => 'active',
        ]);
        $project->salesRepresentatives()->sync([$salesRep->id]);

        $clientUser = User::factory()->create([
            'role' => 'client',
            'customer_id' => $customer->id,
        ]);

        return [$project, $visibleTask, $hiddenTask, $employeeUser, $salesUser, $clientUser];
    }
}
