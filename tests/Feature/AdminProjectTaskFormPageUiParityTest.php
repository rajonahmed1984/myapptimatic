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

class AdminProjectTaskFormPageUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_task_create_and_edit_pages_render_direct_inertia_component(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::query()->create(['name' => 'Task Form Customer']);
        $project = Project::query()->create([
            'name' => 'Task Form Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);
        $task = ProjectTask::query()->create([
            'project_id' => $project->id,
            'title' => 'Task Edit',
            'status' => 'pending',
            'task_type' => 'feature',
            'priority' => 'medium',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.projects.tasks.create', $project))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Projects\\/TaskFormPage', false);

        $this->actingAs($admin)
            ->get(route('admin.projects.tasks.edit', [$project, $task]))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Projects\\/TaskFormPage', false);
    }

    #[Test]
    public function client_role_cannot_access_admin_task_create_or_edit_pages(): void
    {
        $client = User::factory()->create(['role' => Role::CLIENT]);
        $customer = Customer::query()->create(['name' => 'Blocked Task Form Customer']);
        $project = Project::query()->create([
            'name' => 'Blocked Task Form Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);
        $task = ProjectTask::query()->create([
            'project_id' => $project->id,
            'title' => 'Blocked Edit',
            'status' => 'pending',
            'task_type' => 'feature',
            'priority' => 'medium',
        ]);

        $this->actingAs($client)
            ->get(route('admin.projects.tasks.create', $project))
            ->assertForbidden();

        $this->actingAs($client)
            ->get(route('admin.projects.tasks.edit', [$project, $task]))
            ->assertForbidden();
    }
}
