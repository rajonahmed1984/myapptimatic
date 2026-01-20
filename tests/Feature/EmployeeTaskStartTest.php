<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeTaskStartTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function employee_created_task_defaults_to_open_status(): void
    {
        [$project, $employeeUser, $employee] = $this->setupEmployeeProject();

        $payload = [
            'title' => 'Startable task',
            'task_type' => 'feature',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
        ];

        $response = $this->actingAs($employeeUser, 'employee')
            ->post(route('employee.projects.tasks.store', $project), $payload);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('project_tasks', [
            'project_id' => $project->id,
            'title' => 'Startable task',
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function employee_can_start_a_task(): void
    {
        [$project, $task, $employeeUser] = $this->setupEmployeeProjectWithTask();

        $response = $this->actingAs($employeeUser, 'employee')
            ->patch(route('employee.projects.tasks.start', [$project, $task]));

        $response->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame('in_progress', $task->status);
    }

    #[Test]
    public function starting_a_task_twice_is_idempotent(): void
    {
        [$project, $task, $employeeUser] = $this->setupEmployeeProjectWithTask();

        $this->actingAs($employeeUser, 'employee')
            ->patch(route('employee.projects.tasks.start', [$project, $task]))
            ->assertSessionHasNoErrors();

        $this->actingAs($employeeUser, 'employee')
            ->patch(route('employee.projects.tasks.start', [$project, $task]))
            ->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame('in_progress', $task->status);
    }

    private function setupEmployeeProject(): array
    {
        $customer = Customer::create([
            'name' => 'Task Client',
        ]);

        $project = Project::create([
            'name' => 'Employee Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        $employeeUser = User::factory()->create();
        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'name' => 'Employee One',
            'status' => 'active',
        ]);

        $project->employees()->sync([$employee->id]);

        return [$project, $employeeUser, $employee];
    }

    private function setupEmployeeProjectWithTask(): array
    {
        [$project, $employeeUser] = $this->setupEmployeeProject();

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Task One',
            'status' => 'pending',
        ]);

        return [$project, $task, $employeeUser];
    }
}
