<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectChatTest extends TestCase
{
    use RefreshDatabase;

    private function createClientProjectContext(): array
    {
        $customer = Customer::create([
            'name' => 'Chat Client',
        ]);

        $project = Project::create([
            'name' => 'Chat Project',
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

        return [$customer, $project, $client];
    }

    #[Test]
    public function client_can_fetch_incremental_project_chat_messages(): void
    {
        [, $project, $client] = $this->createClientProjectContext();

        $first = ProjectMessage::create([
            'project_id' => $project->id,
            'author_type' => 'user',
            'author_id' => $client->id,
            'message' => 'First',
        ]);

        $second = ProjectMessage::create([
            'project_id' => $project->id,
            'author_type' => 'user',
            'author_id' => $client->id,
            'message' => 'Second',
        ]);

        $response = $this->actingAs($client)
            ->getJson(route('client.projects.chat.messages', [$project, 'after_id' => $first->id]));

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'data' => [
                'items' => [
                    [
                        'id' => $second->id,
                    ],
                ],
            ],
        ]);
    }

    #[Test]
    public function client_cannot_stream_chat_for_unassigned_project(): void
    {
        $customer = Customer::create([
            'name' => 'Primary Client',
        ]);

        $client = User::factory()->create([
            'role' => 'client',
            'customer_id' => $customer->id,
        ]);

        $otherCustomer = Customer::create([
            'name' => 'Other Client',
        ]);

        $project = Project::create([
            'name' => 'Other Project',
            'customer_id' => $otherCustomer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        $response = $this->actingAs($client)
            ->get(route('client.projects.chat.stream', $project));

        $response->assertStatus(403);
    }

    #[Test]
    public function client_can_store_reply_target_in_project_chat_message(): void
    {
        [, $project, $client] = $this->createClientProjectContext();

        $parent = ProjectMessage::create([
            'project_id' => $project->id,
            'author_type' => 'user',
            'author_id' => $client->id,
            'message' => 'Parent message',
        ]);

        $response = $this->actingAs($client)
            ->postJson(route('client.projects.chat.messages.store', $project), [
                'message' => 'Reply message',
                'reply_to_message_id' => $parent->id,
            ]);

        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertDatabaseHas('project_messages', [
            'project_id' => $project->id,
            'message' => 'Reply message',
            'reply_to_message_id' => $parent->id,
        ]);
    }

    #[Test]
    public function client_can_pin_and_unpin_project_chat_message(): void
    {
        [, $project, $client] = $this->createClientProjectContext();

        $message = ProjectMessage::create([
            'project_id' => $project->id,
            'author_type' => 'user',
            'author_id' => $client->id,
            'message' => 'Pin me',
        ]);

        $pinResponse = $this->actingAs($client)
            ->postJson(route('client.projects.chat.messages.pin', [$project, $message]));

        $pinResponse->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.pinned_message_id', $message->id);

        $this->assertDatabaseHas('project_messages', [
            'id' => $message->id,
            'is_pinned' => 1,
            'pinned_by_type' => 'user',
            'pinned_by_id' => $client->id,
        ]);

        $unpinResponse = $this->actingAs($client)
            ->postJson(route('client.projects.chat.messages.pin', [$project, $message]));

        $unpinResponse->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.pinned_message_id', 0);

        $this->assertDatabaseHas('project_messages', [
            'id' => $message->id,
            'is_pinned' => 0,
        ]);
    }

    #[Test]
    public function client_can_toggle_project_chat_reaction(): void
    {
        [, $project, $client] = $this->createClientProjectContext();

        $message = ProjectMessage::create([
            'project_id' => $project->id,
            'author_type' => 'user',
            'author_id' => $client->id,
            'message' => 'React here',
        ]);

        $addResponse = $this->actingAs($client)
            ->postJson(route('client.projects.chat.messages.react', [$project, $message]), [
                'emoji' => '👍',
            ]);

        $addResponse->assertOk()->assertJsonPath('ok', true);

        $message->refresh();
        $this->assertCount(1, (array) $message->reactions);

        $removeResponse = $this->actingAs($client)
            ->postJson(route('client.projects.chat.messages.react', [$project, $message]), [
                'emoji' => '👍',
            ]);

        $removeResponse->assertOk()->assertJsonPath('ok', true);

        $message->refresh();
        $this->assertCount(0, (array) $message->reactions);
    }
}
