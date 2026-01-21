<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectClientAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function project_client_is_limited_to_assigned_project(): void
    {
        $customer = Customer::create(['name' => 'Client', 'status' => 'active']);
        $project = Project::create(['customer_id' => $customer->id, 'name' => 'Assigned project']);
        $otherProject = Project::create(['customer_id' => $customer->id, 'name' => 'Other project']);

        $user = User::factory()->create([
            'role' => Role::CLIENT_PROJECT,
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'password' => Hash::make('secret'),
        ]);

        $this->actingAs($user)
            ->get(route('client.projects.show', $project))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('client.projects.show', $otherProject))
            ->assertStatus(403);
    }

    #[Test]
    public function project_client_cannot_create_task_for_other_project(): void
    {
        $customer = Customer::create(['name' => 'Client', 'status' => 'active']);
        $project = Project::create(['customer_id' => $customer->id, 'name' => 'Assigned project']);
        $otherProject = Project::create(['customer_id' => $customer->id, 'name' => 'Other project']);

        $user = User::factory()->create([
            'role' => Role::CLIENT_PROJECT,
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'password' => Hash::make('secret'),
        ]);

        $this->actingAs($user)
            ->post(route('client.projects.tasks.store', $otherProject), [
                'title' => 'Test task',
                'task_type' => 'feature',
            ])
            ->assertStatus(403);
    }

    #[Test]
    public function project_client_login_redirects_to_assigned_project(): void
    {
        config(['recaptcha.enabled' => false]);

        $customer = Customer::create(['name' => 'Client', 'status' => 'active']);
        $project = Project::create(['customer_id' => $customer->id, 'name' => 'Assigned project']);

        $user = User::factory()->create([
            'role' => Role::CLIENT_PROJECT,
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'status' => 'active',
            'password' => Hash::make('secret'),
        ]);

        $this->post(route('project-client.login.attempt'), [
            'email' => $user->email,
            'password' => 'secret',
        ])->assertRedirect(route('client.projects.show', $project));

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function inactive_project_client_cannot_log_in(): void
    {
        config(['recaptcha.enabled' => false]);

        $customer = Customer::create(['name' => 'Client', 'status' => 'active']);
        $project = Project::create(['customer_id' => $customer->id, 'name' => 'Assigned project']);

        $user = User::factory()->create([
            'role' => Role::CLIENT_PROJECT,
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'status' => 'inactive',
            'password' => Hash::make('secret'),
        ]);

        $this->post(route('project-client.login.attempt'), [
            'email' => $user->email,
            'password' => 'secret',
        ])->assertSessionHasErrors(['email']);

        $this->assertGuest();
    }
}
