<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskSubtask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminTaskCreatorAndAssigneesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_tasks_list_shows_creator_name(): void
    {
        $customer = Customer::create(['name' => 'Creator Client']);
        $project = $this->createProject($customer);
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
            'name' => 'Task Creator',
        ]);

        ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Creator Task',
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.tasks.index'));

        $response->assertOk();

        $content = $response->getContent();
        $this->assertSame(1, preg_match('/data-page="([^"]+)"/', $content, $matches));

        $payload = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);
        $this->assertIsArray($payload);
        $tasks = data_get($payload, 'props.tasks', []);

        $this->assertNotEmpty($tasks);
        $this->assertSame('Task Creator', data_get($tasks[0], 'creator_name'));
    }

    #[Test]
    public function admin_task_detail_shows_subtask_creator_name(): void
    {
        $customer = Customer::create(['name' => 'Subtask Client']);
        $project = $this->createProject($customer);
        $viewer = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
            'name' => 'Viewer Admin',
        ]);
        $creator = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
            'name' => 'Subtask Creator',
        ]);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Task Detail',
            'status' => 'pending',
            'created_by' => $creator->id,
        ]);

        ProjectTaskSubtask::create([
            'project_task_id' => $task->id,
            'title' => 'Subtask Item',
            'is_completed' => false,
            'created_by' => $creator->id,
        ]);

        $response = $this->actingAs($viewer)
            ->get(route('admin.projects.tasks.show', [$project, $task]));

        $response->assertOk();
        $response->assertSee('Added by: Subtask Creator');
    }

    #[Test]
    public function admin_can_assign_multiple_employees_and_sync_task_assignees(): void
    {
        $customer = Customer::create(['name' => 'Assignee Client']);
        $project = $this->createProject($customer);
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Assignee Task',
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);

        $employeeA = Employee::create([
            'name' => 'Employee A',
            'status' => 'active',
        ]);
        $employeeB = Employee::create([
            'name' => 'Employee B',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)
            ->patchJson(route('admin.projects.tasks.assignees', [$project, $task]), [
                'employee_ids' => [$employeeA->id, $employeeB->id, $employeeA->id],
            ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('project_task_assignments', [
            'project_task_id' => $task->id,
            'assignee_type' => 'employee',
            'assignee_id' => $employeeA->id,
        ]);
        $this->assertDatabaseHas('project_task_assignments', [
            'project_task_id' => $task->id,
            'assignee_type' => 'employee',
            'assignee_id' => $employeeB->id,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.projects.tasks.assignees', [$project, $task]), [
                'employee_ids' => [$employeeB->id],
            ])
            ->assertOk();

        $this->assertDatabaseMissing('project_task_assignments', [
            'project_task_id' => $task->id,
            'assignee_type' => 'employee',
            'assignee_id' => $employeeA->id,
        ]);
    }

    #[Test]
    public function non_admin_cannot_update_task_assignees(): void
    {
        $customer = Customer::create(['name' => 'Client']);
        $project = $this->createProject($customer);
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Restricted Task',
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);

        $employee = Employee::create([
            'name' => 'Employee C',
            'status' => 'active',
        ]);

        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($client)
            ->patchJson(route('admin.projects.tasks.assignees', [$project, $task]), [
                'employee_ids' => [$employee->id],
            ])
            ->assertStatus(403);
    }

    private function createProject(Customer $customer, array $overrides = []): Project
    {
        return Project::create(array_merge([
            'name' => 'Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ], $overrides));
    }
}
