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

class ClientTaskDetailViewTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function client_task_detail_hides_subtask_completion_controls(): void
    {
        $customer = Customer::create([
            'name' => 'Client One',
        ]);

        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
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

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Visible Task',
            'status' => 'pending',
            'customer_visible' => true,
        ]);

        ProjectTaskSubtask::create([
            'project_task_id' => $task->id,
            'title' => 'First Subtask',
            'is_completed' => false,
        ]);

        $response = $this->actingAs($client)
            ->get(route('client.projects.tasks.show', [$project, $task]));

        $response->assertOk();
        $response->assertSee('First Subtask');
        $response->assertDontSee('subtask-checkbox');
        $response->assertDontSee('subtask-status-select');
    }
}
