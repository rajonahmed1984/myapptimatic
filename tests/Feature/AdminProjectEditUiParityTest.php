<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminProjectEditUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_project_edit_renders_direct_inertia_component(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::query()->create(['name' => 'Edit Parity Customer']);
        $project = Project::query()->create([
            'name' => 'Edit Parity Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 2000,
            'initial_payment_amount' => 200,
            'currency' => 'USD',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.projects.edit', $project))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Projects\\/Edit', false);
    }

    #[Test]
    public function admin_project_update_validation_and_success_contracts_are_preserved(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::query()->create(['name' => 'Update Parity Customer']);
        $project = Project::query()->create([
            'name' => 'Update Parity Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 3000,
            'initial_payment_amount' => 300,
            'currency' => 'USD',
        ]);
        $editUrl = route('admin.projects.edit', $project);

        $this->actingAs($admin)->from($editUrl)
            ->put(route('admin.projects.update', $project), [
                'name' => '',
                'customer_id' => '',
                'type' => '',
                'status' => '',
                'total_budget' => '',
                'initial_payment_amount' => '',
                'currency' => '',
            ])
            ->assertRedirect($editUrl)
            ->assertSessionHasErrors([
                'name',
                'customer_id',
                'type',
                'status',
                'total_budget',
                'initial_payment_amount',
                'currency',
            ]);

        $this->actingAs($admin)->from($editUrl)
            ->put(route('admin.projects.update', $project), [
                'name' => 'Updated Project Name',
                'customer_id' => $customer->id,
                'type' => 'software',
                'status' => 'ongoing',
                'total_budget' => 3500,
                'initial_payment_amount' => 350,
                'currency' => 'USD',
                'budget_amount' => 1200,
                'software_overhead' => 10,
                'website_overhead' => 20,
                'employee_ids' => [],
                'sales_rep_ids' => [],
            ])
            ->assertRedirect($editUrl)
            ->assertSessionHas('status', 'Project updated.');
    }

    #[Test]
    public function client_role_cannot_access_admin_project_edit(): void
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
            ->get(route('admin.projects.edit', $project))
            ->assertForbidden();
    }
}
