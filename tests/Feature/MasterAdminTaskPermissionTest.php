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
    public function master_admin_can_edit_and_delete_tasks_and_subtasks_regardless_of_age(): void
    {
        $customer = Customer::create([
            'name' => 'Master Admin Customer',
        ]);

        $project = Project::create([
            'name' => 'Master Admin Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Old Task',
            'status' => 'pending',
            'customer_visible' => true,
            'created_by' => $admin->id,
        ]);
        $task->forceFill(['created_at' => now()->subDays(3)])->save();

        $subtask = ProjectTaskSubtask::create([
            'project_task_id' => $task->id,
            'title' => 'Old Subtask',
            'is_completed' => false,
            'created_by' => $admin->id,
        ]);
        $subtask->forceFill(['created_at' => now()->subDays(3)])->save();

        $this->actingAs($admin)
            ->patchJson(route('admin.projects.tasks.update', [$project, $task]), [
                'status' => 'in_progress',
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->patchJson(route('admin.projects.tasks.subtasks.update', [$project, $task, $subtask]), [
                'is_completed' => true,
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->delete(route('admin.projects.tasks.subtasks.destroy', [$project, $task, $subtask]))
            ->assertRedirect();

        $this->assertDatabaseMissing('project_task_subtasks', [
            'id' => $subtask->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.projects.tasks.destroy', [$project, $task]))
            ->assertRedirect();

        $this->assertDatabaseMissing('project_tasks', [
            'id' => $task->id,
        ]);
    }
}
