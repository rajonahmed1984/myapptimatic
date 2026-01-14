<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskMessage;
use App\Models\SalesRepresentative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectTaskChatTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function client_can_chat_on_visible_tasks_and_upload_attachments(): void
    {
        Storage::fake('public');

        [$project, $visibleTask, $hiddenTask, $employeeUser, $salesUser, $clientUser] = $this->setupProjectWithMembers();

        $response = $this->actingAs($clientUser)
            ->get(route('client.projects.tasks.show', [$project, $visibleTask]));

        $response->assertOk();

        $postResponse = $this->actingAs($clientUser)
            ->post(route('client.projects.tasks.activity.store', [$project, $visibleTask]), [
                'message' => 'Client update',
            ]);

        $postResponse->assertRedirect();
        $this->assertDatabaseHas('project_task_activities', [
            'project_task_id' => $visibleTask->id,
            'actor_type' => 'client',
            'actor_id' => $clientUser->id,
            'type' => 'comment',
            'message' => 'Client update',
        ]);

        $fileResponse = $this->actingAs($clientUser)
            ->post(route('client.projects.tasks.upload', [$project, $visibleTask]), [
                'attachment' => UploadedFile::fake()->image('shot.png'),
            ]);

        $fileResponse->assertRedirect();

        $activity = \App\Models\ProjectTaskActivity::latest('id')->first();
        $this->assertNotNull($activity?->attachment_path);
        Storage::disk('public')->assertExists($activity->attachment_path);
        $this->assertDatabaseHas('project_task_activities', [
            'project_task_id' => $visibleTask->id,
            'type' => 'upload',
        ]);
    }

    #[Test]
    public function client_cannot_access_hidden_task_chat(): void
    {
        [$project, $visibleTask, $hiddenTask, $employeeUser, $salesUser, $clientUser] = $this->setupProjectWithMembers();

        $response = $this->actingAs($clientUser)
            ->get(route('client.projects.tasks.show', [$project, $hiddenTask]));

        $response->assertStatus(403);
    }

    #[Test]
    public function employee_can_access_chat_for_project_tasks(): void
    {
        [$project, $visibleTask, $hiddenTask, $employeeUser] = $this->setupProjectWithMembers();

        $response = $this->actingAs($employeeUser, 'employee')
            ->get(route('employee.projects.tasks.show', [$project, $hiddenTask]));

        $response->assertOk();
    }

    #[Test]
    public function chat_messages_endpoint_returns_incremental_results(): void
    {
        [$project, $visibleTask, $hiddenTask, $employeeUser, $salesUser, $clientUser] = $this->setupProjectWithMembers();

        $first = ProjectTaskMessage::create([
            'project_task_id' => $visibleTask->id,
            'author_type' => 'user',
            'author_id' => $clientUser->id,
            'message' => 'First message',
        ]);

        $second = ProjectTaskMessage::create([
            'project_task_id' => $visibleTask->id,
            'author_type' => 'user',
            'author_id' => $clientUser->id,
            'message' => 'Second message',
        ]);

        $response = $this->actingAs($clientUser)
            ->getJson(route('client.projects.tasks.chat.messages', [$project, $visibleTask, 'after_id' => $first->id]));

        $response->assertOk();
        $this->assertTrue($response->json('ok'));
        $this->assertCount(1, $response->json('data.items'));
        $this->assertEquals($second->id, $response->json('data.items.0.id'));
    }

    #[Test]
    public function client_cannot_read_messages_for_hidden_task(): void
    {
        [$project, $visibleTask, $hiddenTask, $employeeUser, $salesUser, $clientUser] = $this->setupProjectWithMembers();

        $response = $this->actingAs($clientUser)
            ->getJson(route('client.projects.tasks.chat.messages', [$project, $hiddenTask]));

        $response->assertStatus(403);
    }

    #[Test]
    public function client_can_send_chat_message_via_json(): void
    {
        [$project, $visibleTask, $hiddenTask, $employeeUser, $salesUser, $clientUser] = $this->setupProjectWithMembers();

        $response = $this->actingAs($clientUser)
            ->postJson(route('client.projects.tasks.chat.messages.store', [$project, $visibleTask]), [
                'message' => 'Hello from json',
            ]);

        $response->assertOk();
        $this->assertTrue($response->json('ok'));
        $this->assertDatabaseHas('project_task_messages', [
            'project_task_id' => $visibleTask->id,
            'author_type' => 'user',
            'author_id' => $clientUser->id,
            'message' => 'Hello from json',
        ]);
    }

    #[Test]
    public function client_can_mark_chat_as_read(): void
    {
        [$project, $visibleTask, $hiddenTask, $employeeUser, $salesUser, $clientUser] = $this->setupProjectWithMembers();

        $first = ProjectTaskMessage::create([
            'project_task_id' => $visibleTask->id,
            'author_type' => 'user',
            'author_id' => $clientUser->id,
            'message' => 'First message',
        ]);

        $second = ProjectTaskMessage::create([
            'project_task_id' => $visibleTask->id,
            'author_type' => 'user',
            'author_id' => $clientUser->id,
            'message' => 'Second message',
        ]);

        $response = $this->actingAs($clientUser)
            ->patchJson(route('client.projects.tasks.chat.read', [$project, $visibleTask]), [
                'last_read_id' => $second->id,
            ]);

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'data' => [
                    'last_read_id' => $second->id,
                    'unread_count' => 0,
                ],
            ]);

        $this->assertDatabaseHas('project_task_message_reads', [
            'project_task_id' => $visibleTask->id,
            'reader_type' => 'user',
            'reader_id' => $clientUser->id,
            'last_read_message_id' => $second->id,
        ]);
    }

    private function setupProjectWithMembers(): array
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

        $visibleTask = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Visible Task',
            'status' => 'pending',
            'customer_visible' => true,
        ]);

        $hiddenTask = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Hidden Task',
            'status' => 'pending',
            'customer_visible' => false,
        ]);

        $employeeUser = User::factory()->create();
        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'name' => 'Employee Two',
            'status' => 'active',
        ]);
        $project->employees()->sync([$employee->id]);

        $salesUser = User::factory()->create(['role' => 'sales']);
        $salesRep = SalesRepresentative::create([
            'user_id' => $salesUser->id,
            'name' => 'Rep Chat',
            'status' => 'active',
        ]);
        $project->salesRepresentatives()->sync([$salesRep->id]);

        $clientUser = User::factory()->create([
            'role' => 'client',
            'customer_id' => $customer->id,
        ]);

        return [$project, $visibleTask, $hiddenTask, $employeeUser, $salesUser, $clientUser];
    }
}
