<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProjectClientMultiProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_project_client_user_with_multiple_projects(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create(['name' => 'Client', 'status' => 'active']);
        $project1 = Project::create(['customer_id' => $customer->id, 'name' => 'Project Alpha']);
        $project2 = Project::create(['customer_id' => $customer->id, 'name' => 'Project Beta']);

        $response = $this->actingAs($admin)
            ->post(route('admin.customers.project-users.store', $customer), [
                'name' => 'Multi Project User',
                'email' => 'multiproject@example.com',
                'password' => 'secret-1234',
                'password_confirmation' => 'secret-1234',
                'project_ids' => [$project1->id, $project2->id],
            ]);

        $response->assertRedirect(route('admin.customers.show', ['customer' => $customer, 'tab' => 'project-specific']));

        $user = User::where('email', 'multiproject@example.com')->firstOrFail();
        $this->assertEquals(Role::CLIENT_PROJECT, $user->role);
        $this->assertEqualsCanonicalizing([$project1->id, $project2->id], $user->assignedProjectIds());
    }

    public function test_admin_can_update_project_client_user_project_assignments(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create(['name' => 'Client', 'status' => 'active']);
        $project1 = Project::create(['customer_id' => $customer->id, 'name' => 'Project Alpha']);
        $project2 = Project::create(['customer_id' => $customer->id, 'name' => 'Project Beta']);
        $project3 = Project::create(['customer_id' => $customer->id, 'name' => 'Project Gamma']);

        $user = User::factory()->create([
            'role' => Role::CLIENT_PROJECT,
            'customer_id' => $customer->id,
            'project_id' => $project1->id,
            'status' => 'active',
        ]);
        $user->projects()->sync([$project1->id]);

        $response = $this->actingAs($admin)
            ->putJson(route('admin.customers.project-users.update', [$customer, $user]), [
                'name' => 'Updated User Name',
                'email' => $user->email,
                'status' => 'active',
                'project_ids' => [$project2->id, $project3->id],
            ]);

        $response->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertEqualsCanonicalizing([$project2->id, $project3->id], $user->fresh()->assignedProjectIds());
    }

    public function test_multi_project_client_can_access_both_assigned_projects(): void
    {
        $customer = Customer::create(['name' => 'Client', 'status' => 'active']);
        $project1 = Project::create(['customer_id' => $customer->id, 'name' => 'Project Alpha']);
        $project2 = Project::create(['customer_id' => $customer->id, 'name' => 'Project Beta']);
        $unassignedProject = Project::create(['customer_id' => $customer->id, 'name' => 'Project Gamma']);

        $user = User::factory()->create([
            'role' => Role::CLIENT_PROJECT,
            'customer_id' => $customer->id,
            'status' => 'active',
            'password' => Hash::make('secret-1234'),
        ]);
        $user->projects()->sync([$project1->id, $project2->id]);

        $this->actingAs($user)
            ->get(route('client.projects.show', $project1))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('client.projects.show', $project2))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('client.projects.show', $unassignedProject))
            ->assertStatus(403);
    }
}
