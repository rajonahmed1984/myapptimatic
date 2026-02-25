<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectTaskActivityJsonEndpointTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function default_activity_items_return_json_payload_without_html(): void
    {
        [$client, $project, $task] = $this->seedClientTask();

        ProjectTaskActivity::create([
            'project_task_id' => $task->id,
            'actor_type' => 'client',
            'actor_id' => $client->id,
            'type' => 'comment',
            'message' => 'Initial activity',
        ]);

        $response = $this->actingAs($client)
            ->getJson(route('client.projects.tasks.activity.items', [$project, $task]));

        $response->assertOk()->assertJsonPath('ok', true);

        $item = (array) ($response->json('data.items.0') ?? []);
        $this->assertArrayNotHasKey('html', $item);
        $this->assertSame('Initial activity', data_get($item, 'activity.message'));
    }

    #[Test]
    public function activity_items_ignore_legacy_marker_and_return_activity_object_without_html(): void
    {
        [$client, $project, $task] = $this->seedClientTask();

        ProjectTaskActivity::create([
            'project_task_id' => $task->id,
            'actor_type' => 'client',
            'actor_id' => $client->id,
            'type' => 'comment',
            'message' => 'Structured activity',
        ]);

        $response = $this->actingAs($client)
            ->getJson(route('client.projects.tasks.activity.items', [
                'project' => $project,
                'task' => $task,
                'structured' => 1,
            ]));

        $response->assertOk()->assertJsonPath('ok', true);

        $item = (array) ($response->json('data.items.0') ?? []);
        $this->assertArrayNotHasKey('html', $item);
        $this->assertSame('Structured activity', data_get($item, 'activity.message'));
        $this->assertSame('comment', data_get($item, 'activity.type'));
        $this->assertNotSame('', (string) data_get($item, 'activity.created_at_display'));
    }

    #[Test]
    public function activity_store_item_ignores_legacy_marker_and_returns_entries_without_html(): void
    {
        [$client, $project, $task] = $this->seedClientTask();

        $response = $this->actingAs($client)
            ->postJson(route('client.projects.tasks.activity.items.store', [
                'project' => $project,
                'task' => $task,
                'structured' => 1,
            ]), [
                'message' => 'Phase D comment',
            ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Comment added.')
            ->assertJsonPath('data.items.0.activity.type', 'comment')
            ->assertJsonPath('data.items.0.activity.message', 'Phase D comment');

        $item = (array) ($response->json('data.items.0') ?? []);
        $this->assertArrayNotHasKey('html', $item);

        $this->assertDatabaseHas('project_task_activities', [
            'project_task_id' => $task->id,
            'actor_type' => 'client',
            'actor_id' => $client->id,
            'type' => 'comment',
            'message' => 'Phase D comment',
        ]);
    }

    /**
     * @return array{User, Project, ProjectTask}
     */
    private function seedClientTask(): array
    {
        $customer = Customer::create(['name' => 'Activity Customer']);
        $project = Project::create([
            'name' => 'Activity Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Activity Task',
            'status' => 'pending',
            'customer_visible' => true,
        ]);

        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        return [$client, $project, $task];
    }
}
