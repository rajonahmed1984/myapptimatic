<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskSubtask;
use App\Models\SalesRepresentative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectTaskSubtaskCreationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function employee_can_create_subtask_with_csrf_token(): void
    {
        $customer = Customer::create(['name' => 'Employee Subtask Customer']);
        $project = Project::create([
            'name' => 'Employee Subtask Project',
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

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Employee Task',
            'status' => 'pending',
            'created_by' => $employeeUser->id,
        ]);

        $token = 'test-token';

        $response = $this->actingAs($employeeUser, 'employee')
            ->withSession(['_token' => $token])
            ->withHeader('X-CSRF-TOKEN', $token)
            ->postJson(route('employee.projects.tasks.subtasks.store', [$project, $task]), [
                'title' => 'Employee Subtask',
                '_token' => $token,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('project_task_subtasks', [
            'project_task_id' => $task->id,
            'title' => 'Employee Subtask',
            'created_by' => $employeeUser->id,
        ]);
    }

    #[Test]
    public function sales_rep_can_create_subtask_on_assigned_project(): void
    {
        $customer = Customer::create(['name' => 'Sales Subtask Customer']);
        $project = Project::create([
            'name' => 'Sales Subtask Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        $salesUser = User::factory()->create(['role' => Role::SALES]);
        $salesRep = SalesRepresentative::create([
            'user_id' => $salesUser->id,
            'name' => 'Sales Rep',
            'status' => 'active',
        ]);
        $project->salesRepresentatives()->sync([$salesRep->id]);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Sales Task',
            'status' => 'pending',
            'created_by' => $salesUser->id,
        ]);

        $token = 'test-token';

        $response = $this->actingAs($salesUser, 'sales')
            ->withSession(['_token' => $token])
            ->withHeader('X-CSRF-TOKEN', $token)
            ->postJson(route('rep.projects.tasks.subtasks.store', [$project, $task]), [
                'title' => 'Sales Subtask',
                '_token' => $token,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('project_task_subtasks', [
            'project_task_id' => $task->id,
            'title' => 'Sales Subtask',
            'created_by' => $salesUser->id,
        ]);
    }

    #[Test]
    public function subtask_validation_returns_json_errors(): void
    {
        $customer = Customer::create(['name' => 'Validation Customer']);
        $project = Project::create([
            'name' => 'Validation Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Validation Task',
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);

        $token = 'test-token';

        $response = $this->actingAs($admin)
            ->withSession(['_token' => $token])
            ->withHeader('X-CSRF-TOKEN', $token)
            ->postJson(route('admin.projects.tasks.subtasks.store', [$project, $task]), [
                'title' => '',
                '_token' => $token,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title']);
    }

    #[Test]
    public function sales_rep_cannot_delete_subtasks(): void
    {
        $customer = Customer::create(['name' => 'Delete Subtask Customer']);
        $project = Project::create([
            'name' => 'Delete Subtask Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        $salesUser = User::factory()->create(['role' => Role::SALES]);
        $salesRep = SalesRepresentative::create([
            'user_id' => $salesUser->id,
            'name' => 'Sales Rep',
            'status' => 'active',
        ]);
        $project->salesRepresentatives()->sync([$salesRep->id]);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Delete Task',
            'status' => 'pending',
            'created_by' => $salesUser->id,
        ]);

        $subtask = ProjectTaskSubtask::create([
            'project_task_id' => $task->id,
            'title' => 'Locked Subtask',
            'created_by' => $salesUser->id,
        ]);

        $response = $this->actingAs($salesUser, 'sales')
            ->delete(route('rep.projects.tasks.subtasks.destroy', [$project, $task, $subtask]));

        $response->assertStatus(403);
        $this->assertDatabaseHas('project_task_subtasks', [
            'id' => $subtask->id,
        ]);
    }
}
