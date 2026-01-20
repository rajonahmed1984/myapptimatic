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

    #[Test]
    public function client_can_fetch_incremental_project_chat_messages(): void
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
}
