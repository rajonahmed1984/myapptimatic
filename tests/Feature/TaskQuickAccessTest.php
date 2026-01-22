<?php

namespace Tests\Feature;

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

class TaskQuickAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_view_all_tasks_in_tasks_index(): void
    {
        $customer = Customer::create(['name' => 'Task Client']);
        $projectA = $this->createProject($customer, ['name' => 'Project A']);
        $projectB = $this->createProject($customer, ['name' => 'Project B']);

        $taskA = ProjectTask::create([
            'project_id' => $projectA->id,
            'title' => 'Task A',
            'status' => 'pending',
        ]);
        $taskB = ProjectTask::create([
            'project_id' => $projectB->id,
            'title' => 'Task B',
            'status' => 'pending',
        ]);

        $admin = User::factory()->create(['role' => 'master_admin']);

        $response = $this->actingAs($admin)->get(route('admin.tasks.index'));

        $response->assertOk();
        $response->assertViewHas('tasks', function ($tasks) use ($taskA, $taskB) {
            $ids = $tasks->getCollection()->pluck('id')->all();
            return in_array($taskA->id, $ids, true) && in_array($taskB->id, $ids, true);
        });
    }

    #[Test]
    public function client_sees_only_customer_visible_tasks_in_tasks_index(): void
    {
        $customer = Customer::create(['name' => 'Visible Client']);
        $project = $this->createProject($customer);

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

        $otherCustomer = Customer::create(['name' => 'Other Client']);
        $otherProject = $this->createProject($otherCustomer, ['name' => 'Other Project']);
        $otherTask = ProjectTask::create([
            'project_id' => $otherProject->id,
            'title' => 'Other Task',
            'status' => 'pending',
            'customer_visible' => true,
        ]);

        $client = User::factory()->create([
            'role' => 'client',
            'customer_id' => $customer->id,
        ]);

        $response = $this->actingAs($client)->get(route('client.tasks.index'));

        $response->assertOk();
        $response->assertViewHas('tasks', function ($tasks) use ($visibleTask, $hiddenTask, $otherTask) {
            $ids = $tasks->getCollection()->pluck('id')->all();
            return in_array($visibleTask->id, $ids, true)
                && ! in_array($hiddenTask->id, $ids, true)
                && ! in_array($otherTask->id, $ids, true);
        });
    }

    #[Test]
    public function employee_sees_project_and_assigned_tasks_only_in_tasks_index(): void
    {
        $customer = Customer::create(['name' => 'Employee Client']);
        $project = $this->createProject($customer, ['name' => 'Employee Project']);
        $otherProject = $this->createProject($customer, ['name' => 'Other Project']);

        $employeeUser = User::factory()->create();
        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'name' => 'Employee One',
            'status' => 'active',
        ]);
        $project->employees()->sync([$employee->id]);

        $projectTask = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Project Task',
            'status' => 'pending',
        ]);
        $assignedTask = ProjectTask::create([
            'project_id' => $otherProject->id,
            'title' => 'Assigned Task',
            'status' => 'pending',
            'assigned_type' => 'employee',
            'assigned_id' => $employee->id,
        ]);
        $otherTask = ProjectTask::create([
            'project_id' => $otherProject->id,
            'title' => 'Other Task',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($employeeUser, 'employee')
            ->get(route('employee.tasks.index'));

        $response->assertOk();
        $response->assertViewHas('tasks', function ($tasks) use ($projectTask, $assignedTask, $otherTask) {
            $ids = $tasks->getCollection()->pluck('id')->all();
            return in_array($projectTask->id, $ids, true)
                && in_array($assignedTask->id, $ids, true)
                && ! in_array($otherTask->id, $ids, true);
        });
    }

    #[Test]
    public function sales_rep_sees_project_and_assigned_tasks_only_in_tasks_index(): void
    {
        $customer = Customer::create(['name' => 'Sales Client']);
        $project = $this->createProject($customer, ['name' => 'Sales Project']);
        $otherProject = $this->createProject($customer, ['name' => 'Other Sales Project']);

        $salesUser = User::factory()->create(['role' => 'sales']);
        $salesRep = SalesRepresentative::create([
            'user_id' => $salesUser->id,
            'name' => 'Sales Rep',
            'status' => 'active',
        ]);
        $project->salesRepresentatives()->sync([$salesRep->id]);

        $projectTask = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Project Task',
            'status' => 'pending',
        ]);
        $assignedTask = ProjectTask::create([
            'project_id' => $otherProject->id,
            'title' => 'Assigned Task',
            'status' => 'pending',
            'assigned_type' => 'sales_rep',
            'assigned_id' => $salesRep->id,
        ]);
        $otherTask = ProjectTask::create([
            'project_id' => $otherProject->id,
            'title' => 'Other Task',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($salesUser, 'sales')
            ->get(route('rep.tasks.index'));

        $response->assertOk();
        $response->assertViewHas('tasks', function ($tasks) use ($projectTask, $assignedTask, $otherTask) {
            $ids = $tasks->getCollection()->pluck('id')->all();
            return in_array($projectTask->id, $ids, true)
                && in_array($assignedTask->id, $ids, true)
                && ! in_array($otherTask->id, $ids, true);
        });
    }

    #[Test]
    public function tasks_are_ordered_latest_first_in_tasks_index(): void
    {
        $customer = Customer::create(['name' => 'Ordering Client']);
        $project = $this->createProject($customer, ['name' => 'Ordering Project']);

        $oldest = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Oldest Task',
            'status' => 'pending',
        ]);
        $oldest->forceFill([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ])->save();

        $middle = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Middle Task',
            'status' => 'pending',
        ]);
        $middle->forceFill([
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ])->save();

        $newest = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Newest Task',
            'status' => 'pending',
        ]);

        $admin = User::factory()->create(['role' => 'master_admin']);

        $response = $this->actingAs($admin)->get(route('admin.tasks.index'));

        $response->assertOk();
        $ids = $response->viewData('tasks')->getCollection()->pluck('id')->all();
        $this->assertSame([$newest->id, $middle->id, $oldest->id], $ids);
    }

    #[Test]
    public function employee_can_start_open_task_when_allowed(): void
    {
        $customer = Customer::create(['name' => 'Start Client']);
        $project = $this->createProject($customer, ['name' => 'Start Project']);

        $employeeUser = User::factory()->create();
        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'name' => 'Start Employee',
            'status' => 'active',
        ]);
        $project->employees()->sync([$employee->id]);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Start Task',
            'status' => 'pending',
            'created_by' => $employeeUser->id,
            'assigned_type' => 'employee',
            'assigned_id' => $employee->id,
        ]);

        $response = $this->actingAs($employeeUser, 'employee')
            ->patch(route('employee.projects.tasks.start', [$project, $task]));

        $response->assertRedirect();
        $this->assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => 'in_progress',
        ]);
    }

    #[Test]
    public function client_cannot_start_task_without_update_permission(): void
    {
        $customer = Customer::create(['name' => 'Client Start']);
        $project = $this->createProject($customer, ['name' => 'Client Project']);

        $creator = User::factory()->create(['role' => 'master_admin']);
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Client Task',
            'status' => 'pending',
            'customer_visible' => true,
            'created_by' => $creator->id,
        ]);

        $client = User::factory()->create([
            'role' => 'client',
            'customer_id' => $customer->id,
        ]);

        $response = $this->actingAs($client)
            ->patch(route('client.projects.tasks.update', [$project, $task]), [
                'status' => 'in_progress',
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function employee_cannot_complete_task_with_subtasks(): void
    {
        $customer = Customer::create(['name' => 'Completion Client']);
        $project = $this->createProject($customer, ['name' => 'Completion Project']);

        $employeeUser = User::factory()->create();
        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'name' => 'Completion Employee',
            'status' => 'active',
        ]);
        $project->employees()->sync([$employee->id]);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Task With Subtasks',
            'status' => 'in_progress',
            'created_by' => $employeeUser->id,
        ]);

        ProjectTaskSubtask::create([
            'project_task_id' => $task->id,
            'title' => 'Subtask 1',
        ]);

        $response = $this->actingAs($employeeUser, 'employee')
            ->patch(route('employee.projects.tasks.update', [$project, $task]), [
                'status' => 'completed',
            ]);

        $response->assertSessionHasErrors('status');
        $this->assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => 'in_progress',
        ]);
    }

    #[Test]
    public function dashboard_widget_appears_only_when_tasks_visible(): void
    {
        $employeeUser = User::factory()->create();
        Employee::create([
            'user_id' => $employeeUser->id,
            'name' => 'Widget Employee',
            'status' => 'active',
        ]);

        $employeeResponse = $this->actingAs($employeeUser, 'employee')
            ->get(route('employee.dashboard'));

        $employeeResponse->assertOk();
        $employeeResponse->assertSee('My Open Tasks');

        $supportUser = User::factory()->create(['role' => 'support']);
        $supportResponse = $this->actingAs($supportUser, 'support')
            ->get(route('support.dashboard'));

        $supportResponse->assertOk();
        $supportResponse->assertDontSee('My Open Tasks');
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
