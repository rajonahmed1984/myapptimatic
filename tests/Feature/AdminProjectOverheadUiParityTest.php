<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectOverhead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminProjectOverheadUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_project_overhead_index_renders_direct_inertia_component(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $customer = Customer::create([
            'name' => 'Overhead UI Customer',
            'email' => 'overhead-ui@example.test',
            'status' => 'active',
        ]);

        $project = Project::create([
            'name' => 'Overhead UI Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'currency' => 'USD',
        ]);

        ProjectOverhead::create([
            'project_id' => $project->id,
            'short_details' => 'Setup Fee',
            'amount' => 120,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.projects.overheads.index', $project))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Projects\\/Overheads\\/Index', false);
    }

    #[Test]
    public function project_overhead_store_and_destroy_contracts_are_preserved(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $customer = Customer::create([
            'name' => 'Overhead Contract Customer',
            'email' => 'overhead-contract@example.test',
            'status' => 'active',
        ]);

        $project = Project::create([
            'name' => 'Overhead Contract Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'currency' => 'USD',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.projects.overheads.store', $project), [
                'short_details' => 'Initial Overhead',
                'amount' => '99.99',
            ])
            ->assertRedirect(route('admin.projects.overheads.index', $project))
            ->assertSessionHas('success', 'Overhead added successfully.');

        $overhead = ProjectOverhead::query()->where('project_id', $project->id)->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('admin.projects.overheads.destroy', [$project, $overhead]))
            ->assertRedirect(route('admin.projects.overheads.index', $project))
            ->assertSessionHas('success', 'Overhead deleted successfully.');
    }
}
