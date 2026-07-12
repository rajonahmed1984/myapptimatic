<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminProjectTaskFormPageUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_task_create_and_edit_pages_render_direct_inertia_component(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::query()->create(['name' => 'Task Form Customer']);
        $project = Project::query()->create([
            'name' => 'Task Form Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);
        $task = ProjectTask::query()->create([
            'project_id' => $project->id,
            'title' => 'Task Edit',
            'status' => 'pending',
            'task_type' => 'feature',
            'priority' => 'medium',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.projects.tasks.create', $project))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Projects\\/TaskFormPage', false);

        $this->actingAs($admin)
            ->get(route('admin.projects.tasks.edit', [$project, $task]))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Projects\\/TaskFormPage', false);
    }

    #[Test]
    public function client_role_cannot_access_admin_task_create_or_edit_pages(): void
    {
        $client = User::factory()->create(['role' => Role::CLIENT]);
        $customer = Customer::query()->create(['name' => 'Blocked Task Form Customer']);
        $project = Project::query()->create([
            'name' => 'Blocked Task Form Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);
        $task = ProjectTask::query()->create([
            'project_id' => $project->id,
            'title' => 'Blocked Edit',
            'status' => 'pending',
            'task_type' => 'feature',
            'priority' => 'medium',
        ]);

        $this->actingAs($client)
            ->get(route('admin.projects.tasks.create', $project))
            ->assertForbidden();

        $this->actingAs($client)
            ->get(route('admin.projects.tasks.edit', [$project, $task]))
            ->assertForbidden();
    }

    #[Test]
    public function master_admin_can_update_task_title_and_description_from_edit_page(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::query()->create(['name' => 'Task Update Customer']);
        $project = Project::query()->create([
            'name' => 'Task Update Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);
        $task = ProjectTask::query()->create([
            'project_id' => $project->id,
            'title' => 'Old task title',
            'description' => 'Old task details',
            'status' => 'pending',
            'task_type' => 'feature',
            'priority' => 'medium',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.projects.tasks.update', [$project, $task]), [
                'title' => 'Updated task title',
                'description' => 'Updated task details',
                'status' => 'pending',
                'task_type' => 'feature',
                'priority' => 'high',
                'progress' => 25,
                'customer_visible' => 1,
            ])
            ->assertRedirect();

        $task->refresh();
        $this->assertSame('Updated task title', $task->title);
        $this->assertSame('Updated task details', $task->description);
        $this->assertSame('high', $task->priority);
        $this->assertSame(25, $task->progress);
    }

    #[Test]
    public function master_admin_can_clear_task_description(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::query()->create(['name' => 'Clear Desc Customer']);
        $project = Project::query()->create([
            'name' => 'Clear Desc Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);
        $task = ProjectTask::query()->create([
            'project_id' => $project->id,
            'title' => 'Task Title',
            'description' => 'Some old description',
            'status' => 'pending',
            'task_type' => 'feature',
            'priority' => 'medium',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.projects.tasks.update', [$project, $task]), [
                'title' => 'Task Title',
                'description' => '',
                'status' => 'pending',
            ])
            ->assertRedirect();

        $task->refresh();
        $this->assertNull($task->description);
    }

    #[Test]
    public function client_can_update_task_title_and_description_and_clear_description(): void
    {
        $customer = Customer::query()->create(['name' => 'Client Update Customer']);
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);
        $project = Project::query()->create([
            'name' => 'Client Update Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);
        $task = ProjectTask::query()->create([
            'project_id' => $project->id,
            'title' => 'Old Title',
            'description' => 'Old Description',
            'status' => 'pending',
            'customer_visible' => true,
            'created_by' => $client->id,
        ]);

        // 1. Update title and description
        $this->actingAs($client)
            ->patch(route('client.projects.tasks.update', [$project, $task]), [
                'title' => 'Client New Title',
                'description' => 'Client New Description',
                'status' => 'pending',
            ])
            ->assertRedirect();

        $task->refresh();
        $this->assertSame('Client New Title', $task->title);
        $this->assertSame('Client New Description', $task->description);

        // 2. Clear description
        $this->actingAs($client)
            ->patch(route('client.projects.tasks.update', [$project, $task]), [
                'title' => 'Client New Title',
                'description' => '',
                'status' => 'pending',
            ])
            ->assertRedirect();

        $task->refresh();
        $this->assertNull($task->description);
    }

    #[Test]
    public function employee_creator_can_update_task_title_and_description_and_clear_description(): void
    {
        $customer = Customer::query()->create(['name' => 'Employee Update Customer']);
        $employeeUser = User::factory()->create(['role' => Role::EMPLOYEE]);
        $employee = \App\Models\Employee::create([
            'user_id' => $employeeUser->id,
            'name' => 'Employee Name',
            'status' => 'active',
        ]);
        $project = Project::query()->create([
            'name' => 'Employee Update Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);
        $project->employees()->sync([$employee->id]);

        $task = ProjectTask::query()->create([
            'project_id' => $project->id,
            'title' => 'Old Title',
            'description' => 'Old Description',
            'status' => 'pending',
            'created_by' => $employeeUser->id,
        ]);

        // 1. Update title and description
        $this->actingAs($employeeUser, 'employee')
            ->patch(route('employee.projects.tasks.update', [$project, $task]), [
                'title' => 'Employee New Title',
                'description' => 'Employee New Description',
                'status' => 'pending',
            ])
            ->assertRedirect();

        $task->refresh();
        $this->assertSame('Employee New Title', $task->title);
        $this->assertSame('Employee New Description', $task->description);

        // 2. Clear description
        $this->actingAs($employeeUser, 'employee')
            ->patch(route('employee.projects.tasks.update', [$project, $task]), [
                'title' => 'Employee New Title',
                'description' => '',
                'status' => 'pending',
            ])
            ->assertRedirect();

        $task->refresh();
        $this->assertNull($task->description);
    }

    #[Test]
    public function sales_rep_creator_can_update_task_title_and_description_and_clear_description(): void
    {
        $customer = Customer::query()->create(['name' => 'SalesRep Update Customer']);
        $salesUser = User::factory()->create(['role' => Role::SALES]);
        $salesRep = \App\Models\SalesRepresentative::create([
            'user_id' => $salesUser->id,
            'name' => 'SalesRep Name',
            'email' => $salesUser->email,
            'status' => 'active',
        ]);
        $project = Project::query()->create([
            'name' => 'SalesRep Update Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);
        $project->salesRepresentatives()->sync([$salesRep->id]);

        $task = ProjectTask::query()->create([
            'project_id' => $project->id,
            'title' => 'Old Title',
            'description' => 'Old Description',
            'status' => 'pending',
            'created_by' => $salesUser->id,
        ]);

        // 1. Update title and description
        $this->actingAs($salesUser, 'sales')
            ->patch(route('rep.projects.tasks.update', [$project, $task]), [
                'title' => 'Sales New Title',
                'description' => 'Sales New Description',
                'status' => 'pending',
            ])
            ->assertRedirect();

        $task->refresh();
        $this->assertSame('Sales New Title', $task->title);
        $this->assertSame('Sales New Description', $task->description);

        // 2. Clear description
        $this->actingAs($salesUser, 'sales')
            ->patch(route('rep.projects.tasks.update', [$project, $task]), [
                'title' => 'Sales New Title',
                'description' => '',
                'status' => 'pending',
            ])
            ->assertRedirect();

        $task->refresh();
        $this->assertNull($task->description);
    }
}
