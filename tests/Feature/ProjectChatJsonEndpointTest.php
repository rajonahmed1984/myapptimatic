<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectChatJsonEndpointTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function default_project_chat_messages_return_message_payload_without_html(): void
    {
        [$client, $project] = $this->seedClientProject();

        ProjectMessage::create([
            'project_id' => $project->id,
            'author_type' => 'user',
            'author_id' => $client->id,
            'message' => 'Project chat hello',
        ]);

        $response = $this->actingAs($client)
            ->getJson(route('client.projects.chat.messages', $project));

        $response->assertOk()->assertJsonPath('ok', true);

        $item = (array) ($response->json('data.items.0') ?? []);
        $this->assertArrayNotHasKey('html', $item);
        $this->assertArrayHasKey('message', $item);
        $this->assertSame('Project chat hello', data_get($item, 'message.message'));
    }

    #[Test]
    public function project_chat_messages_ignore_legacy_marker_and_omit_html(): void
    {
        [$client, $project] = $this->seedClientProject();

        ProjectMessage::create([
            'project_id' => $project->id,
            'author_type' => 'user',
            'author_id' => $client->id,
            'message' => 'Structured project chat',
        ]);

        $response = $this->actingAs($client)
            ->getJson(route('client.projects.chat.messages', [
                'project' => $project,
                'structured' => 1,
            ]));

        $response->assertOk()->assertJsonPath('ok', true);

        $item = (array) ($response->json('data.items.0') ?? []);
        $this->assertArrayNotHasKey('html', $item);
        $this->assertSame('Structured project chat', data_get($item, 'message.message'));
        $this->assertSame('User', data_get($item, 'message.author_type_label'));
    }

    #[Test]
    public function project_chat_store_message_ignores_legacy_marker_and_omits_html(): void
    {
        [$client, $project] = $this->seedClientProject();

        $response = $this->actingAs($client)
            ->postJson(route('client.projects.chat.messages.store', [
                'project' => $project,
                'structured' => 1,
            ]), [
                'message' => 'Stored project chat',
            ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.item.message.message', 'Stored project chat');

        $item = (array) ($response->json('data.item') ?? []);
        $this->assertArrayNotHasKey('html', $item);
    }

    /**
     * @return array{User, Project}
     */
    private function seedClientProject(): array
    {
        $customer = Customer::create(['name' => 'Project Chat Structured']);

        $project = Project::create([
            'name' => 'Project Chat Project',
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
