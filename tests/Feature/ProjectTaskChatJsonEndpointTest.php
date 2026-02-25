<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectTaskChatJsonEndpointTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function default_task_chat_messages_return_message_payload_without_html(): void
    {
        [$client, $project, $task] = $this->seedClientTask();

        ProjectTaskMessage::create([
            'project_task_id' => $task->id,
            'author_type' => 'user',
            'author_id' => $client->id,
            'message' => 'Task chat hello',
        ]);

        $response = $this->actingAs($client)
            ->getJson(route('client.projects.tasks.chat.messages', [$project, $task]));

        $response->assertOk()->assertJsonPath('ok', true);

        $item = (array) ($response->json('data.items.0') ?? []);
        $this->assertArrayNotHasKey('html', $item);
        $this->assertArrayHasKey('message', $item);
        $this->assertSame('Task chat hello', data_get($item, 'message.message'));
    }

    #[Test]
    public function task_chat_messages_ignore_legacy_marker_and_omit_html(): void
    {
        [$client, $project, $task] = $this->seedClientTask();

        ProjectTaskMessage::create([
            'project_task_id' => $task->id,
            'author_type' => 'user',
            'author_id' => $client->id,
            'message' => 'Structured task chat',
        ]);

        $response = $this->actingAs($client)
            ->getJson(route('client.projects.tasks.chat.messages', [
                'project' => $project,
                'task' => $task,
                'structured' => 1,
            ]));

        $response->assertOk()->assertJsonPath('ok', true);

        $item = (array) ($response->json('data.items.0') ?? []);
        $this->assertArrayNotHasKey('html', $item);
        $this->assertSame('Structured task chat', data_get($item, 'message.message'));
        $this->assertSame('User', data_get($item, 'message.author_type_label'));
    }

    #[Test]
    public function task_chat_store_message_ignores_legacy_marker_and_omits_html(): void
    {
        [$client, $project, $task] = $this->seedClientTask();

        $response = $this->actingAs($client)
            ->postJson(route('client.projects.tasks.chat.messages.store', [
                'project' => $project,
                'task' => $task,
                'structured' => 1,
            ]), [
                'message' => 'Stored task chat',
            ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.item.message.message', 'Stored task chat');

        $item = (array) ($response->json('data.item') ?? []);
        $this->assertArrayNotHasKey('html', $item);
    }

    /**
     * @return array{User, Project, ProjectTask}
     */
    private function seedClientTask(): array
    {
        $customer = Customer::create(['name' => 'Task Chat Structured']);

        $project = Project::create([
            'name' => 'Task Chat Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Task Chat Task',
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
