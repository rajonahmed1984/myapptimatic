<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskSubtask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MasterAdminTaskPermissionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function master_admin_can_update_task_status_after_24_hours(): void
    {
        $project = $this->createProject();
        $creator = User::factory()->create(['role' => Role::SUB_ADMIN]);
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Old Task',
            'status' => 'pending',
            'customer_visible' => true,
            'created_by' => $creator->id,
        ]);
        $task->forceFill(['created_at' => now()->subDays(3)])->save();

        $response = $this->actingAs($admin)
            ->patchJson(route('admin.projects.tasks.update', [$project, $task]), [
                'status' => 'in_progress',
            ]);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => 'in_progress',
        ]);
    }

    #[Test]
    public function master_admin_can_update_subtask_status_after_24_hours(): void
    {
        $project = $this->createProject();
        $creator = User::factory()->create(['role' => Role::SUB_ADMIN]);
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Task With Subtask',
            'status' => 'pending',
            'customer_visible' => true,
            'created_by' => $creator->id,
        ]);

        $subtask = ProjectTaskSubtask::create([
            'project_task_id' => $task->id,
            'title' => 'Old Subtask',
            'is_completed' => false,
            'created_by' => $creator->id,
        ]);
        $subtask->forceFill(['created_at' => now()->subDays(3)])->save();

        $response = $this->actingAs($admin)
            ->patchJson(route('admin.projects.tasks.subtasks.update', [$project, $task, $subtask]), [
                'is_completed' => true,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('project_task_subtasks', [
            'id' => $subtask->id,
            'is_completed' => true,
        ]);
    }

    #[Test]
    public function master_admin_can_delete_task_created_by_another_user(): void
    {
        $project = $this->createProject();
        $creator = User::factory()->create(['role' => Role::SUB_ADMIN]);
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Completed Task',
            'status' => 'completed',
            'completed_at' => now(),
            'customer_visible' => true,
            'created_by' => $creator->id,
        ]);
        $task->forceFill(['created_at' => now()->subDays(3)])->save();

        $response = $this->actingAs($admin)
            ->delete(route('admin.projects.tasks.destroy', [$project, $task]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('project_tasks', [
            'id' => $task->id,
        ]);
    }

    #[Test]
    public function master_admin_can_delete_subtask_created_by_another_user(): void
    {
        $project = $this->createProject();
        $creator = User::factory()->create(['role' => Role::SUB_ADMIN]);
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Task For Subtask Delete',
            'status' => 'pending',
            'customer_visible' => true,
            'created_by' => $creator->id,
        ]);

        $subtask = ProjectTaskSubtask::create([
            'project_task_id' => $task->id,
            'title' => 'Delete Subtask',
            'is_completed' => false,
            'created_by' => $creator->id,
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.projects.tasks.subtasks.destroy', [$project, $task, $subtask]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('project_task_subtasks', [
            'id' => $subtask->id,
        ]);
    }

    #[Test]
    public function sub_admin_non_creator_cannot_update_task(): void
    {
        $project = $this->createProject();
        $creator = User::factory()->create(['role' => Role::SUB_ADMIN]);
        $actor = User::factory()->create(['role' => Role::SUB_ADMIN]);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Locked Task',
            'status' => 'pending',
            'customer_visible' => true,
            'created_by' => $creator->id,
        ]);

        $response = $this->actingAs($actor)
            ->patchJson(route('admin.projects.tasks.update', [$project, $task]), [
                'status' => 'in_progress',
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function sub_admin_non_creator_cannot_delete_task(): void
    {
        $project = $this->createProject();
        $creator = User::factory()->create(['role' => Role::SUB_ADMIN]);
        $actor = User::factory()->create(['role' => Role::SUB_ADMIN]);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Locked Delete Task',
            'status' => 'pending',
            'customer_visible' => true,
            'created_by' => $creator->id,
        ]);

        $response = $this->actingAs($actor)
            ->delete(route('admin.projects.tasks.destroy', [$project, $task]));

        $response->assertStatus(403);
    }

    private function createProject(): Project
    {
        $customer = Customer::create([
            'name' => 'Permission Customer',
        ]);

        return Project::create([
            'name' => 'Permission Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);
    }
}
