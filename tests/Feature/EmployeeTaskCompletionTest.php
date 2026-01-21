<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskSubtask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeTaskCompletionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function task_without_subtasks_can_be_completed_manually(): void
    {
        [$project, $task, $employeeUser] = $this->setupEmployeeProject();

        $response = $this->actingAs($employeeUser, 'employee')
            ->patch(route('employee.projects.tasks.update', [$project, $task]), [
                'status' => 'completed',
            ]);

        $response->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertNotNull($task->completed_at);
    }

    #[Test]
    public function task_with_subtasks_cannot_be_completed_manually(): void
    {
        [$project, $task, $employeeUser] = $this->setupEmployeeProject();

        ProjectTaskSubtask::create([
            'project_task_id' => $task->id,
            'title' => 'Subtask A',
            'is_completed' => false,
            'created_by' => $employeeUser->id,
        ]);

        $response = $this->actingAs($employeeUser, 'employee')
            ->patch(route('employee.projects.tasks.update', [$project, $task]), [
                'status' => 'completed',
            ]);

        $response->assertSessionHasErrors('status');

        $task->refresh();
        $this->assertSame('pending', $task->status);
        $this->assertNull($task->completed_at);
    }

    #[Test]
    public function completing_last_subtask_auto_completes_parent_task(): void
    {
        [$project, $task, $employeeUser] = $this->setupEmployeeProject();

        $first = ProjectTaskSubtask::create([
            'project_task_id' => $task->id,
            'title' => 'Subtask A',
            'is_completed' => false,
            'created_by' => $employeeUser->id,
        ]);

        $second = ProjectTaskSubtask::create([
            'project_task_id' => $task->id,
            'title' => 'Subtask B',
            'is_completed' => false,
            'created_by' => $employeeUser->id,
        ]);

        $this->actingAs($employeeUser, 'employee')
            ->patch(route('employee.projects.tasks.subtasks.update', [$project, $task, $first]), [
                'is_completed' => true,
            ])->assertSessionHasNoErrors();

        $this->actingAs($employeeUser, 'employee')
            ->patch(route('employee.projects.tasks.subtasks.update', [$project, $task, $second]), [
                'is_completed' => true,
            ])->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertNotNull($task->completed_at);
    }

    #[Test]
    public function reverting_subtask_marks_parent_incomplete(): void
    {
        [$project, $task, $employeeUser] = $this->setupEmployeeProject();

        $first = ProjectTaskSubtask::create([
            'project_task_id' => $task->id,
            'title' => 'Subtask A',
            'is_completed' => true,
            'completed_at' => now(),
            'created_by' => $employeeUser->id,
        ]);

        $second = ProjectTaskSubtask::create([
            'project_task_id' => $task->id,
            'title' => 'Subtask B',
            'is_completed' => true,
            'completed_at' => now(),
            'created_by' => $employeeUser->id,
        ]);

        $this->actingAs($employeeUser, 'employee')
            ->patch(route('employee.projects.tasks.subtasks.update', [$project, $task, $first]), [
                'is_completed' => true,
            ])->assertSessionHasNoErrors();

        $this->actingAs($employeeUser, 'employee')
            ->patch(route('employee.projects.tasks.subtasks.update', [$project, $task, $second]), [
                'is_completed' => true,
            ])->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame('completed', $task->status);

        $this->actingAs($employeeUser, 'employee')
            ->patch(route('employee.projects.tasks.subtasks.update', [$project, $task, $second]), [
                'is_completed' => false,
            ])->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame('in_progress', $task->status);
        $this->assertNull($task->completed_at);
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

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Task One',
            'status' => 'pending',
            'created_by' => $employeeUser->id,
        ]);

        $project->employees()->sync([$employee->id]);

        return [$project, $task, $employeeUser];
    }
}
