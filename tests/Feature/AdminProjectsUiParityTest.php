<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminProjectsUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_projects_pages_render_direct_inertia_components(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $customer = Customer::create([
            'name' => 'Parity Project Customer',
            'email' => 'parity-project-customer@example.test',
            'status' => 'active',
        ]);

        Project::create([
            'customer_id' => $customer->id,
            'name' => 'Parity Project',
            'type' => 'software',
            'status' => 'ongoing',
        ]);

        $project = Project::create([
            'customer_id' => $customer->id,
            'name' => 'Parity Project',
            'type' => 'software',
            'status' => 'ongoing',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.projects.index'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Projects\\/Index', false);

        $this->actingAs($admin)
            ->get(route('admin.projects.show', $project))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Projects\\/Show', false);
    }

    #[Test]
    public function admin_project_destroy_contract_is_preserved(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $customer = Customer::create([
            'name' => 'Destroy Project Customer',
            'email' => 'destroy-project-customer@example.test',
            'status' => 'active',
        ]);

        $project = Project::create([
            'customer_id' => $customer->id,
            'name' => 'Destroy Me',
            'type' => 'software',
            'status' => 'ongoing',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.projects.destroy', $project))
            ->assertRedirect(route('admin.projects.index'))
            ->assertSessionHas('status', 'Project deleted.');

        $this->assertSoftDeleted('projects', [
            'id' => $project->id,
        ]);
    }

    #[Test]
    public function client_role_cannot_access_admin_projects_index(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        $this->actingAs($client)
            ->get(route('admin.projects.index'))
            ->assertForbidden();
    }
}
