<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminProjectTasksUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_project_tasks_index_renders_direct_inertia_component(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::query()->create(['name' => 'Tasks Customer']);
        $project = Project::query()->create([
            'name' => 'Tasks Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);
        ProjectTask::query()->create([
            'project_id' => $project->id,
            'title' => 'Task A',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.projects.tasks.index', $project))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Projects\\/Tasks', false);
    }

    #[Test]
    public function client_role_cannot_access_admin_project_tasks_index(): void
    {
        $client = User::factory()->create(['role' => Role::CLIENT]);
        $customer = Customer::query()->create(['name' => 'Blocked Customer']);
        $project = Project::query()->create([
            'name' => 'Blocked Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        $this->actingAs($client)
            ->get(route('admin.projects.tasks.index', $project))
            ->assertForbidden();
    }
}
