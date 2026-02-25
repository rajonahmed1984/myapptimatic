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

class AdminLegacyBridgeUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function legacy_admin_blade_pages_are_bridged_to_inertia_component(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);
        $customer = Customer::query()->create(['name' => 'Legacy Bridge Customer']);
        $project = Project::query()->create([
            'name' => 'Legacy Bridge Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);
        $task = ProjectTask::query()->create([
            'project_id' => $project->id,
            'title' => 'Legacy bridge task',
            'status' => 'pending',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.projects.tasks.show', [$project, $task]))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Projects\\/TaskDetailClickup', false);
    }
}
