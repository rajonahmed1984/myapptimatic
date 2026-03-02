<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskSubtask;
use App\Models\ProjectTaskSubtaskComment;
use App\Models\User;
use App\Notifications\SubtaskCommentSummaryNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectTaskSubtaskCommentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function client_can_comment_and_reply_on_subtask(): void
    {
        [$project, $task, $subtask, $client] = $this->seedClientVisibleTask();

        $commentResponse = $this->actingAs($client)
            ->postJson(route('client.projects.tasks.subtasks.comments.store', [$project, $task, $subtask]), [
                'message' => 'Client main comment',
            ]);

        $commentResponse->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Comment added.');

        $commentId = (int) $commentResponse->json('data.comment.id');

        $this->assertDatabaseHas('project_task_subtask_comments', [
            'id' => $commentId,
            'project_task_id' => $task->id,
            'project_task_subtask_id' => $subtask->id,
            'parent_id' => null,
            'actor_type' => 'client',
            'actor_id' => $client->id,
            'message' => 'Client main comment',
        ]);

        $replyResponse = $this->actingAs($client)
            ->postJson(route('client.projects.tasks.subtasks.comments.store', [$project, $task, $subtask]), [
                'parent_id' => $commentId,
                'message' => 'Client reply comment',
            ]);

        $replyResponse->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.comment.parent_id', $commentId);

        $this->assertDatabaseHas('project_task_subtask_comments', [
            'project_task_subtask_id' => $subtask->id,
            'parent_id' => $commentId,
            'message' => 'Client reply comment',
        ]);
    }

    #[Test]
    public function task_detail_includes_subtask_comment_trail(): void
    {
        [$project, $task, $subtask, $client] = $this->seedClientVisibleTask();
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $root = ProjectTaskSubtaskComment::create([
            'project_task_id' => $task->id,
            'project_task_subtask_id' => $subtask->id,
            'actor_type' => 'client',
            'actor_id' => $client->id,
            'message' => 'Root trail comment',
        ]);

        ProjectTaskSubtaskComment::create([
            'project_task_id' => $task->id,
            'project_task_subtask_id' => $subtask->id,
            'parent_id' => $root->id,
            'actor_type' => 'client',
            'actor_id' => $client->id,
            'message' => 'Reply trail comment',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.projects.tasks.show', [$project, $task]));

        $response->assertOk();
        $response->assertSee('Root trail comment');
        $response->assertSee('Reply trail comment');
    }

    #[Test]
    public function subtask_comment_sends_summary_email_to_customer_client_and_master_admin(): void
    {
        Notification::fake();

        $customer = Customer::create([
            'name' => 'Notify Customer',
            'email' => 'customer@example.test',
        ]);

        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
            'email' => 'client@example.test',
        ]);

        $masterAdmin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
            'email' => 'master-admin@example.test',
        ]);

        $project = Project::create([
            'name' => 'Notify Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Notify Task',
            'status' => 'pending',
            'customer_visible' => true,
            'created_by' => $masterAdmin->id,
        ]);

        $subtask = ProjectTaskSubtask::create([
            'project_task_id' => $task->id,
            'title' => 'Notify Subtask',
            'created_by' => $masterAdmin->id,
        ]);

        $this->actingAs($masterAdmin)
            ->post(route('admin.projects.tasks.subtasks.comments.store', [$project, $task, $subtask]), [
                'message' => 'Notification comment',
            ])
            ->assertRedirect();

        Notification::assertSentOnDemand(SubtaskCommentSummaryNotification::class, function ($notification, $channels, $notifiable) {
            return ($notifiable->routes['mail'] ?? null) === 'customer@example.test';
        });
        Notification::assertSentOnDemand(SubtaskCommentSummaryNotification::class, function ($notification, $channels, $notifiable) {
            return ($notifiable->routes['mail'] ?? null) === 'client@example.test';
        });
        Notification::assertSentOnDemand(SubtaskCommentSummaryNotification::class, function ($notification, $channels, $notifiable) {
            return ($notifiable->routes['mail'] ?? null) === 'master-admin@example.test';
        });
        Notification::assertSentOnDemandTimes(SubtaskCommentSummaryNotification::class, 3);
    }

    /**
     * @return array{Project, ProjectTask, ProjectTaskSubtask, User}
     */
    private function seedClientVisibleTask(): array
    {
        $customer = Customer::create([
            'name' => 'Task Customer',
            'email' => 'task-customer@example.test',
        ]);

        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
            'email' => 'task-client@example.test',
        ]);

        $masterAdmin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $project = Project::create([
            'name' => 'Comment Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Comment Task',
            'status' => 'pending',
            'customer_visible' => true,
            'created_by' => $masterAdmin->id,
        ]);

        $subtask = ProjectTaskSubtask::create([
            'project_task_id' => $task->id,
            'title' => 'Comment Subtask',
            'created_by' => $masterAdmin->id,
        ]);

        return [$project, $task, $subtask, $client];
    }
}
