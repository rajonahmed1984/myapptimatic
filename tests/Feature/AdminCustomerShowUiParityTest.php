<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminCustomerShowUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_customer_show_renders_direct_inertia_component(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create([
            'name' => 'Show Parity Customer',
            'email' => 'show-parity-customer@example.test',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Customers\\/Show', false);
    }

    #[Test]
    public function customer_show_invalid_tab_falls_back_to_summary_contract(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create([
            'name' => 'Fallback Tab Customer',
            'email' => 'fallback-tab-customer@example.test',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.customers.show', ['customer' => $customer, 'tab' => 'unknown']))
            ->assertOk()
            ->assertSee('Recent Tickets')
            ->assertDontSee('Update project-specific login');
    }

    #[Test]
    public function customer_project_login_create_redirect_contract_is_preserved(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create([
            'name' => 'Project Login Customer',
            'email' => 'project-login-customer@example.test',
            'status' => 'active',
        ]);
        $project = Project::create([
            'customer_id' => $customer->id,
            'name' => 'Project Login Target',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.customers.project-users.store', $customer), [
                'project_id' => $project->id,
                'name' => 'Project User',
                'email' => 'project-user@example.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertRedirect(route('admin.customers.show', ['customer' => $customer, 'tab' => 'project-specific']))
            ->assertSessionHas('status', 'Project client user created.');

        $this->assertDatabaseHas('users', [
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'email' => 'project-user@example.test',
            'role' => Role::CLIENT_PROJECT,
        ]);
    }

    #[Test]
    public function customer_project_login_create_validation_contract_is_preserved(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create([
            'name' => 'Project Validation Customer',
            'email' => 'project-validation-customer@example.test',
            'status' => 'active',
        ]);
        $showUrl = route('admin.customers.show', ['customer' => $customer, 'tab' => 'project-specific']);

        $this->actingAs($admin)
            ->from($showUrl)
            ->post(route('admin.customers.project-users.store', $customer), [])
            ->assertRedirect($showUrl)
            ->assertSessionHasErrors([
                'name',
                'email',
                'password',
                'project_id',
            ]);
    }

    #[Test]
    public function customer_impersonate_redirect_contract_is_preserved(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create([
            'name' => 'Impersonate Customer',
            'email' => 'impersonate-customer@example.test',
            'status' => 'active',
        ]);

        User::factory()->create([
            'name' => 'Customer User',
            'email' => $customer->email,
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.customers.impersonate', $customer))
            ->assertRedirect(route('client.dashboard'));
    }

    #[Test]
    public function client_role_cannot_access_admin_customer_show(): void
    {
        $client = User::factory()->create(['role' => Role::CLIENT]);
        $customer = Customer::create([
            'name' => 'Forbidden Customer',
            'email' => 'forbidden-customer@example.test',
            'status' => 'active',
        ]);

        $this->actingAs($client)
            ->get(route('admin.customers.show', $customer))
            ->assertForbidden();
    }
}
