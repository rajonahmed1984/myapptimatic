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

class ClientTaskPermissionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function client_creator_can_edit_task_within_24_hours(): void
    {
        [$client, $project] = $this->setupClientProject();

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Client Task',
            'status' => 'pending',
            'customer_visible' => true,
            'created_by' => $client->id,
        ]);
        $task->forceFill(['created_at' => now()->subHour()])->save();

        $response = $this->actingAs($client)
            ->patch(route('client.projects.tasks.update', [$project, $task]), [
                'status' => 'in_progress',
            ]);

        $response->assertSessionHasNoErrors();
        $task->refresh();
        $this->assertSame('in_progress', $task->status);
    }

    #[Test]
    public function client_creator_cannot_edit_task_after_24_hours(): void
    {
        [$client, $project] = $this->setupClientProject();

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Old Client Task',
            'status' => 'pending',
            'customer_visible' => true,
            'created_by' => $client->id,
        ]);
        $task->forceFill(['created_at' => now()->subDays(2)])->save();

        $response = $this->actingAs($client)
            ->patchJson(route('client.projects.tasks.update', [$project, $task]), [
                'status' => 'in_progress',
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'message' => 'You can only edit this task within 24 hours of creation.',
        ]);
    }

    #[Test]
    public function client_creator_can_edit_subtask_within_24_hours(): void
    {
        [$client, $project] = $this->setupClientProject();

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Task With Subtask',
            'status' => 'pending',
            'customer_visible' => true,
            'created_by' => $client->id,
        ]);

        $subtask = ProjectTaskSubtask::create([
            'project_task_id' => $task->id,
            'title' => 'Client Subtask',
            'is_completed' => false,
            'created_by' => $client->id,
        ]);
        $subtask->forceFill(['created_at' => now()->subHour()])->save();

        $response = $this->actingAs($client)
            ->patchJson(route('client.projects.tasks.subtasks.update', [$project, $task, $subtask]), [
                'is_completed' => true,
            ]);

        $response->assertOk();
        $subtask->refresh();
        $this->assertTrue((bool) $subtask->is_completed);
    }

    #[Test]
    public function client_creator_cannot_edit_subtask_after_24_hours(): void
    {
        [$client, $project] = $this->setupClientProject();

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Task With Old Subtask',
            'status' => 'pending',
            'customer_visible' => true,
            'created_by' => $client->id,
        ]);

        $subtask = ProjectTaskSubtask::create([
            'project_task_id' => $task->id,
            'title' => 'Old Subtask',
            'is_completed' => false,
            'created_by' => $client->id,
        ]);
        $subtask->forceFill(['created_at' => now()->subDays(2)])->save();

        $response = $this->actingAs($client)
            ->patchJson(route('client.projects.tasks.subtasks.update', [$project, $task, $subtask]), [
                'is_completed' => true,
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'message' => 'You can only edit this subtask within 24 hours of creation.',
        ]);
    }

    #[Test]
    public function client_can_create_subtask_for_visible_task(): void
    {
        [$client, $project] = $this->setupClientProject();

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Visible Task',
            'status' => 'pending',
            'customer_visible' => true,
        ]);

        $response = $this->actingAs($client)
            ->postJson(route('client.projects.tasks.subtasks.store', [$project, $task]), [
                'title' => 'New Subtask',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('project_task_subtasks', [
            'project_task_id' => $task->id,
            'title' => 'New Subtask',
            'created_by' => $client->id,
        ]);
        $subtask = ProjectTaskSubtask::where('project_task_id', $task->id)->latest('id')->first();
        $this->assertNotNull($subtask);
        $this->assertNull($subtask->due_date);
        $this->assertNull($subtask->due_time);
    }

    #[Test]
    public function client_task_created_via_client_flow_defaults_to_pending_status(): void
    {
        [$client, $project] = $this->setupClientProject();

        $response = $this->actingAs($client)
            ->post(route('client.projects.tasks.store', $project), [
                'title' => 'Client Created Task',
                'task_type' => 'feature',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('project_tasks', [
            'project_id' => $project->id,
            'title' => 'Client Created Task',
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function client_subtask_edit_controls_show_open_label_for_recent_subtask(): void
    {
        [$client, $project] = $this->setupClientProject();

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Visible Task',
            'status' => 'pending',
            'customer_visible' => true,
            'created_by' => $client->id,
        ]);

        $this->actingAs($client)
            ->postJson(route('client.projects.tasks.subtasks.store', [$project, $task]), [
                'title' => 'Open Subtask',
            ])
            ->assertStatus(201);

        $response = $this->actingAs($client)
            ->get(route('client.projects.tasks.show', [$project, $task]));

        $response->assertOk();
        $response->assertSee('subtask-edit-btn', false);
        $response->assertSee('>Open<', false);
    }

    #[Test]
    public function client_cannot_create_subtask_for_hidden_task(): void
    {
        [$client, $project] = $this->setupClientProject();

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Hidden Task',
            'status' => 'pending',
            'customer_visible' => false,
        ]);

        $response = $this->actingAs($client)
            ->postJson(route('client.projects.tasks.subtasks.store', [$project, $task]), [
                'title' => 'Should Fail',
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function client_task_list_shows_edit_link_for_recent_own_task(): void
    {
        [$client, $project] = $this->setupClientProject();

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Editable Task',
            'status' => 'pending',
            'customer_visible' => true,
            'created_by' => $client->id,
        ]);
        $task->forceFill(['created_at' => now()->subHour()])->save();

        $response = $this->actingAs($client)
            ->get(route('client.projects.show', $project));

        $response->assertOk();
        $response->assertSee(str_replace('/', '\\/', route('client.projects.tasks.show', [$project, $task])) . '#task-edit', false);
    }

    #[Test]
    public function client_task_list_hides_edit_link_for_other_users_task(): void
    {
        [$client, $project] = $this->setupClientProject();

        $otherUser = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $project->customer_id,
        ]);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Other Task',
            'status' => 'pending',
            'customer_visible' => true,
            'created_by' => $otherUser->id,
        ]);

        $response = $this->actingAs($client)
            ->get(route('client.projects.show', $project));

        $response->assertOk();
        $response->assertDontSee(str_replace('/', '\\/', route('client.projects.tasks.show', [$project, $task])) . '#task-edit', false);
    }

    #[Test]
    public function client_task_detail_shows_edit_form_for_recent_own_task(): void
    {
        [$client, $project] = $this->setupClientProject();

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Editable Detail Task',
            'status' => 'pending',
            'customer_visible' => true,
            'created_by' => $client->id,
        ]);
        $task->forceFill(['created_at' => now()->subHour()])->save();

        $response = $this->actingAs($client)
            ->get(route('client.projects.tasks.show', [$project, $task]));

        $response->assertOk();
        $response->assertSee('id="task-edit"', false);
    }

    private function setupClientProject(): array
    {
        $customer = Customer::create([
            'name' => 'Client Customer',
        ]);

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
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        return [$client, $project];
    }
}
