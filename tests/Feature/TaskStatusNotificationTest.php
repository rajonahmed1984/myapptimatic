<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskSubtask;
use App\Models\User;
use App\Notifications\TaskStatusCompletedNotification;
use App\Notifications\TaskStatusOpenedNotification;
use App\Support\TaskAssignmentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TaskStatusNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['task-notifications.enabled' => true]);
    }

    public function test_task_created_sends_open_notification_to_related_users(): void
    {
        Notification::fake();

        [$project, $clientUser, $masterAdmin, $employee] = $this->createProjectWithMembers();

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Open Task',
            'status' => 'pending',
            'customer_visible' => true,
            'assigned_type' => 'employee',
            'assigned_id' => $employee->id,
            'created_by' => $masterAdmin->id,
        ]);

        TaskAssignmentManager::sync($task, [
            ['type' => 'employee', 'id' => $employee->id],
        ]);

        Notification::assertSentOnDemand(TaskStatusOpenedNotification::class, function ($notification, $channels, $notifiable) use ($masterAdmin) {
            return ($notifiable->routes['mail'] ?? null) === $masterAdmin->email;
        });
        Notification::assertSentOnDemand(TaskStatusOpenedNotification::class, function ($notification, $channels, $notifiable) use ($employee) {
            return ($notifiable->routes['mail'] ?? null) === $employee->email;
        });
        Notification::assertSentOnDemand(TaskStatusOpenedNotification::class, function ($notification, $channels, $notifiable) use ($clientUser) {
            return ($notifiable->routes['mail'] ?? null) === $clientUser->email;
        });
        Notification::assertSentOnDemandTimes(TaskStatusOpenedNotification::class, 3);
    }

    public function test_task_completed_sends_completed_notification(): void
    {
        Notification::fake();

        [$project, $clientUser, $masterAdmin, $employee] = $this->createProjectWithMembers();

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Done Task',
            'status' => 'pending',
            'customer_visible' => true,
            'assigned_type' => 'employee',
            'assigned_id' => $employee->id,
            'created_by' => $masterAdmin->id,
        ]);

        TaskAssignmentManager::sync($task, [
            ['type' => 'employee', 'id' => $employee->id],
        ]);

        Notification::fake();

        $task->update(['status' => 'completed']);

        Notification::assertSentOnDemand(TaskStatusCompletedNotification::class, function ($notification, $channels, $notifiable) use ($masterAdmin) {
            return ($notifiable->routes['mail'] ?? null) === $masterAdmin->email;
        });
        Notification::assertSentOnDemand(TaskStatusCompletedNotification::class, function ($notification, $channels, $notifiable) use ($employee) {
            return ($notifiable->routes['mail'] ?? null) === $employee->email;
        });
        Notification::assertSentOnDemand(TaskStatusCompletedNotification::class, function ($notification, $channels, $notifiable) use ($clientUser) {
            return ($notifiable->routes['mail'] ?? null) === $clientUser->email;
        });
        Notification::assertSentOnDemandTimes(TaskStatusCompletedNotification::class, 3);
    }

    public function test_subtask_open_and_complete_notifications_send(): void
    {
        Notification::fake();

        [$project, $clientUser, $masterAdmin, $employee] = $this->createProjectWithMembers();

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Parent Task',
            'status' => 'pending',
            'customer_visible' => true,
            'assigned_type' => 'employee',
            'assigned_id' => $employee->id,
            'created_by' => $masterAdmin->id,
        ]);

        TaskAssignmentManager::sync($task, [
            ['type' => 'employee', 'id' => $employee->id],
        ]);

        Notification::fake();

        $subtask = ProjectTaskSubtask::create([
            'project_task_id' => $task->id,
            'title' => 'Subtask One',
            'status' => 'open',
            'created_by' => $clientUser->id,
        ]);

        Notification::assertSentOnDemandTimes(TaskStatusOpenedNotification::class, 3);

        Notification::fake();

        ProjectTaskSubtask::create([
            'project_task_id' => $task->id,
            'title' => 'Subtask Two',
            'status' => 'open',
            'created_by' => $masterAdmin->id,
        ]);

        Notification::fake();

        $subtask->update([
            'status' => 'completed',
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        Notification::assertSentOnDemandTimes(TaskStatusCompletedNotification::class, 3);
    }

    public function test_parent_completion_notification_fires_when_all_subtasks_complete(): void
    {
        Notification::fake();

        [$project, $clientUser, $masterAdmin, $employee] = $this->createProjectWithMembers();

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Parent Task Complete',
            'status' => 'pending',
            'customer_visible' => true,
            'assigned_type' => 'employee',
            'assigned_id' => $employee->id,
            'created_by' => $masterAdmin->id,
        ]);

        TaskAssignmentManager::sync($task, [
            ['type' => 'employee', 'id' => $employee->id],
        ]);

        Notification::fake();

        $subtask = ProjectTaskSubtask::create([
            'project_task_id' => $task->id,
            'title' => 'Final Subtask',
            'status' => 'open',
            'created_by' => $clientUser->id,
        ]);

        Notification::fake();

        $this->actingAs($masterAdmin)
            ->patch(route('admin.projects.tasks.subtasks.update', [$project, $task, $subtask]), [
                'status' => 'completed',
            ])
            ->assertStatus(302);

        Notification::assertSentOnDemandTimes(TaskStatusCompletedNotification::class, 6);
    }

    public function test_hidden_tasks_do_not_notify_clients(): void
    {
        Notification::fake();

        [$project, $clientUser, $masterAdmin, $employee] = $this->createProjectWithMembers();

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Hidden Task',
            'status' => 'pending',
            'customer_visible' => false,
            'assigned_type' => 'employee',
            'assigned_id' => $employee->id,
            'created_by' => $masterAdmin->id,
        ]);

        TaskAssignmentManager::sync($task, [
            ['type' => 'employee', 'id' => $employee->id],
        ]);

        Notification::assertNotSentTo(new AnonymousNotifiable, TaskStatusOpenedNotification::class, function ($notification, $channels, $notifiable) use ($clientUser) {
            return ($notifiable->routes['mail'] ?? null) === $clientUser->email;
        });
        Notification::assertSentOnDemandTimes(TaskStatusOpenedNotification::class, 2);
    }

    public function test_status_update_with_same_value_sends_no_notification(): void
    {
        Notification::fake();

        [$project, $clientUser, $masterAdmin, $employee] = $this->createProjectWithMembers();

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Static Task',
            'status' => 'pending',
            'customer_visible' => true,
            'assigned_type' => 'employee',
            'assigned_id' => $employee->id,
            'created_by' => $masterAdmin->id,
        ]);

        TaskAssignmentManager::sync($task, [
            ['type' => 'employee', 'id' => $employee->id],
        ]);

        Notification::fake();

        $task->update(['status' => 'pending']);

        Notification::assertNothingSent();
    }

    public function test_creator_is_not_duplicated_in_recipient_list(): void
    {
        Notification::fake();

        [$project, $clientUser, $masterAdmin, $employee] = $this->createProjectWithMembers();

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Dedup Task',
            'status' => 'pending',
            'customer_visible' => false,
            'assigned_type' => 'employee',
            'assigned_id' => $employee->id,
            'created_by' => $masterAdmin->id,
        ]);

        TaskAssignmentManager::sync($task, [
            ['type' => 'employee', 'id' => $employee->id],
        ]);

        Notification::assertSentOnDemandTimes(TaskStatusOpenedNotification::class, 2);
    }

    private function createProjectWithMembers(): array
    {
        $customer = Customer::create([
            'name' => 'Task Client',
            'email' => 'client@example.com',
        ]);

        $clientUser = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
            'email' => 'client@example.com',
        ]);

        $masterAdmin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
            'email' => 'admin@example.com',
        ]);

        $project = Project::create([
            'name' => 'Task Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        $employee = Employee::create([
            'name' => 'Task Employee',
            'email' => 'employee@example.com',
            'status' => 'active',
        ]);

        return [$project, $clientUser, $masterAdmin, $employee];
    }
}
