<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskSubtask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectTaskEditWindowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function task_creator_can_edit_within_24_hours(): void
    {
        $customer = Customer::create(['name' => 'Edit Window Customer']);
        $project = Project::create([
            'name' => 'Edit Window Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);
        $admin = User::factory()->create(['role' => 'master_admin']);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Fresh task',
            'status' => 'pending',
            'task_type' => 'feature',
            'priority' => 'medium',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'created_by' => $admin->id,
        ]);
        $task->forceFill(['created_at' => now()->subHour()])->save();

        $response = $this->actingAs($admin)
            ->patchJson(route('admin.projects.tasks.update', [$project, $task]), [
                'status' => 'in_progress',
            ]);

        $response->assertOk();
        $this->assertTrue($response->json('ok'));
        $this->assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => 'in_progress',
        ]);
    }

    #[Test]
    public function task_creator_cannot_edit_after_24_hours(): void
    {
        $customer = Customer::create(['name' => 'Expired Customer']);
        $project = Project::create([
            'name' => 'Expired Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);
        $admin = User::factory()->create(['role' => 'master_admin']);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Old task',
            'status' => 'pending',
            'task_type' => 'feature',
            'priority' => 'medium',
            'start_date' => now()->addDays(5)->toDateString(),
            'due_date' => now()->addDays(6)->toDateString(),
            'created_by' => $admin->id,
        ]);
        $task->forceFill(['created_at' => now()->subDays(2)])->save();

        $response = $this->actingAs($admin)
            ->patchJson(route('admin.projects.tasks.update', [$project, $task]), [
                'status' => 'in_progress',
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'message' => 'Task can only be edited within 24 hours of creation.',
        ]);
    }

    #[Test]
    public function client_task_detail_does_not_show_subtask_checkbox(): void
    {
        $customer = Customer::create(['name' => 'Client Customer']);
        $project = Project::create([
            'name' => 'Client Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);
        $client = User::factory()->create([
            'role' => 'client',
            'customer_id' => $customer->id,
        ]);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Visible task',
            'status' => 'pending',
            'task_type' => 'feature',
            'priority' => 'medium',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'customer_visible' => true,
            'created_by' => $client->id,
        ]);

        ProjectTaskSubtask::create([
            'project_task_id' => $task->id,
            'title' => 'Subtask item',
        ]);

        $response = $this->actingAs($client)
            ->get(route('client.projects.tasks.show', [$project, $task]));

        $response->assertOk();
        $response->assertDontSee('subtask-checkbox');
    }

    #[Test]
    public function adding_subtask_updates_parent_completion_state(): void
    {
        $customer = Customer::create(['name' => 'Subtask Customer']);
        $project = Project::create([
            'name' => 'Subtask Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);
        $admin = User::factory()->create(['role' => 'master_admin']);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Completed task',
            'status' => 'completed',
            'task_type' => 'feature',
            'priority' => 'medium',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'completed_at' => now(),
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.projects.tasks.subtasks.store', [$project, $task]), [
                'title' => 'New subtask',
            ]);

        $response->assertStatus(201);
        $task->refresh();

        $this->assertNotEquals('completed', $task->status);
        $this->assertNull($task->completed_at);
    }
}
