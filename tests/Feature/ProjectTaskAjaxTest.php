<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectTaskAjaxTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_create_update_and_change_status_via_json(): void
    {
        $customer = Customer::create([
            'name' => 'Ajax Client',
        ]);

        $project = Project::create([
            'name' => 'Ajax Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        $admin = User::factory()->create([
            'role' => 'master_admin',
        ]);

        $employeeUser = User::factory()->create();
        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'name' => 'Ajax Employee',
            'status' => 'active',
        ]);

        $payload = [
            'title' => 'Ajax Task',
            'task_type' => 'feature',
            'priority' => 'medium',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'assignee' => 'employee:' . $employee->id,
            'customer_visible' => 1,
        ];

        $storeResponse = $this->actingAs($admin)
            ->postJson(route('admin.projects.tasks.store', $project), $payload);

        $storeResponse->assertOk();
        $this->assertTrue($storeResponse->json('ok'));
        $this->assertDatabaseHas('project_tasks', [
            'project_id' => $project->id,
            'title' => 'Ajax Task',
        ]);

        $task = ProjectTask::where('project_id', $project->id)->latest('id')->firstOrFail();

        $updateResponse = $this->actingAs($admin)
            ->patchJson(route('admin.projects.tasks.update', [$project, $task]), [
                'status' => 'in_progress',
                'progress' => 45,
            ]);

        $updateResponse->assertOk();
        $this->assertTrue($updateResponse->json('ok'));
        $this->assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => 'in_progress',
            'progress' => 45,
        ]);

        $statusResponse = $this->actingAs($admin)
            ->patchJson(route('admin.projects.tasks.changeStatus', [$project, $task]), [
                'status' => 'completed',
                'progress' => 100,
            ]);

        $statusResponse->assertOk();
        $this->assertTrue($statusResponse->json('ok'));
        $this->assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => 'completed',
            'progress' => 100,
        ]);
    }

    #[Test]
    public function client_cannot_create_tasks_for_other_customers(): void
    {
        $customer = Customer::create([
            'name' => 'Project Customer',
        ]);

        $project = Project::create([
            'name' => 'Project Locked',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        $otherCustomer = Customer::create([
            'name' => 'Other Customer',
        ]);

        $client = User::factory()->create([
            'role' => 'client',
            'customer_id' => $otherCustomer->id,
        ]);

        $response = $this->actingAs($client)
            ->postJson(route('client.projects.tasks.store', $project), [
                'title' => 'Should fail',
                'task_type' => 'feature',
                'priority' => 'medium',
            ]);

        $response->assertStatus(403);
    }
}
