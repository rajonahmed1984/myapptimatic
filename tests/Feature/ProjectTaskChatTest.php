<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTask;
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
