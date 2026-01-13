<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectTaskActivityLinkTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function comment_with_url_creates_link_activity(): void
    {
        $customer = Customer::create([
            'name' => 'Link Client',
        ]);

        $project = Project::create([
            'name' => 'Link Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Link Task',
            'status' => 'pending',
            'customer_visible' => true,
        ]);

        $clientUser = User::factory()->create([
            'role' => 'client',
            'customer_id' => $customer->id,
        ]);

        $message = 'Check https://example.com/docs for updates.';

        $response = $this->actingAs($clientUser)
            ->post(route('client.projects.tasks.activity.store', [$project, $task]), [
                'message' => $message,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('project_task_activities', [
            'project_task_id' => $task->id,
            'type' => 'comment',
            'message' => $message,
        ]);

        $this->assertDatabaseHas('project_task_activities', [
            'project_task_id' => $task->id,
            'type' => 'link',
        ]);
    }
}
