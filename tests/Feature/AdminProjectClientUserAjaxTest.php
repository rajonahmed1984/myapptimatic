<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProjectClientUserAjaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_fetch_project_client_json(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create(['name' => 'Client', 'status' => 'active']);
        $project = Project::create(['customer_id' => $customer->id, 'name' => 'Assigned project']);
        $user = User::factory()->create([
            'role' => Role::CLIENT_PROJECT,
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.customers.project-users.show', [$customer, $user]))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'status' => 'active',
                    'project_id' => $project->id,
                ],
            ]);
    }

    public function test_admin_can_update_project_client_profile_and_status(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create(['name' => 'Client', 'status' => 'active']);
        $project = Project::create(['customer_id' => $customer->id, 'name' => 'Assigned project']);
        $user = User::factory()->create([
            'role' => Role::CLIENT_PROJECT,
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->putJson(route('admin.customers.project-users.update', [$customer, $user]), [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
                'project_id' => $project->id,
                'status' => 'inactive',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'status' => 'inactive',
        ]);
    }

    public function test_admin_can_update_project_client_password_and_login(): void
    {
        config(['recaptcha.enabled' => false]);

        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create(['name' => 'Client', 'status' => 'active']);
        $project = Project::create(['customer_id' => $customer->id, 'name' => 'Assigned project']);
        $user = User::factory()->create([
            'role' => Role::CLIENT_PROJECT,
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'status' => 'active',
            'password' => Hash::make('old-secret'),
        ]);

        $this->actingAs($admin)
            ->putJson(route('admin.customers.project-users.update', [$customer, $user]), [
                'name' => 'Project Client',
                'email' => 'project-client@example.com',
                'project_id' => $project->id,
                'status' => 'active',
                'password' => 'new-secret-123',
                'password_confirmation' => 'new-secret-123',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $user->refresh();
        $this->assertTrue(Hash::check('new-secret-123', $user->password));

        Auth::logout();

        $this->post(route('project-client.login.attempt'), [
            'email' => $user->email,
            'password' => 'new-secret-123',
        ])->assertRedirect(route('client.projects.show', $project));

        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_update_with_empty_password_keeps_existing_hash(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create(['name' => 'Client', 'status' => 'active']);
        $project = Project::create(['customer_id' => $customer->id, 'name' => 'Assigned project']);
        $user = User::factory()->create([
            'role' => Role::CLIENT_PROJECT,
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'status' => 'active',
            'password' => Hash::make('original-secret'),
        ]);
        $originalHash = $user->password;

        $this->actingAs($admin)
            ->putJson(route('admin.customers.project-users.update', [$customer, $user]), [
                'name' => 'Project Client',
                'email' => 'keep-password@example.com',
                'project_id' => $project->id,
                'status' => 'active',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame($originalHash, $user->fresh()->password);
    }

    public function test_admin_cannot_update_project_client_for_other_customer(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create(['name' => 'Client A', 'status' => 'active']);
        $otherCustomer = Customer::create(['name' => 'Client B', 'status' => 'active']);
        $customerProject = Project::create(['customer_id' => $customer->id, 'name' => 'Customer project']);
        $project = Project::create(['customer_id' => $otherCustomer->id, 'name' => 'Other project']);
        $user = User::factory()->create([
            'role' => Role::CLIENT_PROJECT,
            'customer_id' => $otherCustomer->id,
            'project_id' => $project->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->putJson(route('admin.customers.project-users.update', [$customer, $user]), [
                'name' => 'Nope',
                'email' => 'nope@example.com',
                'project_id' => $customerProject->id,
                'status' => 'active',
            ])
            ->assertStatus(404);
    }

    public function test_non_admin_cannot_access_project_client_endpoints(): void
    {
        $client = User::factory()->create(['role' => Role::CLIENT]);
        $customer = Customer::create(['name' => 'Client', 'status' => 'active']);
        $project = Project::create(['customer_id' => $customer->id, 'name' => 'Assigned project']);
        $user = User::factory()->create([
            'role' => Role::CLIENT_PROJECT,
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'status' => 'active',
        ]);

        $this->actingAs($client)
            ->getJson(route('admin.customers.project-users.show', [$customer, $user]))
            ->assertStatus(403);

        $this->actingAs($client)
            ->putJson(route('admin.customers.project-users.update', [$customer, $user]), [
                'name' => 'Blocked',
                'email' => 'blocked@example.com',
                'project_id' => $project->id,
                'status' => 'active',
            ])
            ->assertStatus(403);
    }
}
