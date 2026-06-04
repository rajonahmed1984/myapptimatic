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

class AdminTaskStatusButtonsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_task_detail_enables_start_and_complete_permissions_when_no_subtasks(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $customer = Customer::create([
            'name' => 'Test Customer',
        ]);

        $project = Project::create([
            'name' => 'Test Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Main Task Without Subtasks',
            'status' => 'pending',
            'customer_visible' => true,
        ]);

        // Request details show page
        $response = $this->actingAs($admin)
            ->get(route('admin.projects.tasks.show', [$project, $task]));

        $response->assertOk();

        // Decode Inertia response to assert permissions
        preg_match('/data-page="([^"]+)"/', $response->getContent(), $matches);
        $this->assertArrayHasKey(1, $matches);
        $decoded = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        $payload = json_decode($decoded, true);

        $this->assertTrue(data_get($payload, 'props.permissions.canStartTask'));
        $this->assertTrue(data_get($payload, 'props.permissions.canCompleteTask'));
    }

    #[Test]
    public function admin_task_detail_disables_start_and_complete_permissions_when_subtasks_exist(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $customer = Customer::create([
            'name' => 'Test Customer',
        ]);

        $project = Project::create([
            'name' => 'Test Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Main Task With Subtasks',
            'status' => 'pending',
            'customer_visible' => true,
        ]);

        ProjectTaskSubtask::create([
            'project_task_id' => $task->id,
            'title' => 'Child Subtask',
            'is_completed' => false,
            'created_by' => $admin->id,
        ]);

        // Request details show page
        $response = $this->actingAs($admin)
            ->get(route('admin.projects.tasks.show', [$project, $task]));

        $response->assertOk();

        // Decode Inertia response to assert permissions
        preg_match('/data-page="([^"]+)"/', $response->getContent(), $matches);
        $this->assertArrayHasKey(1, $matches);
        $decoded = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        $payload = json_decode($decoded, true);

        $this->assertFalse(data_get($payload, 'props.permissions.canStartTask'));
        $this->assertFalse(data_get($payload, 'props.permissions.canCompleteTask'));
    }
}
