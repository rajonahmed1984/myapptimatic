<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectMaintenance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectMaintenancesUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function project_maintenance_pages_render_direct_inertia_components(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        [$customer, $project] = $this->createProjectWithCustomer();

        $maintenance = ProjectMaintenance::create([
            'project_id' => $project->id,
            'customer_id' => $customer->id,
            'title' => 'Parity Maintenance',
            'amount' => 150,
            'currency' => 'USD',
            'billing_cycle' => 'monthly',
            'start_date' => now()->toDateString(),
            'next_billing_date' => now()->addMonth()->toDateString(),
            'status' => 'active',
            'auto_invoice' => true,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.project-maintenances.index'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/ProjectMaintenances\\/Index', false);

        $this->actingAs($admin)
            ->get(route('admin.project-maintenances.create'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/ProjectMaintenances\\/Form', false);

        $this->actingAs($admin)
            ->get(route('admin.project-maintenances.edit', $maintenance))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/ProjectMaintenances\\/Form', false);

        $this->actingAs($admin)
            ->get(route('admin.project-maintenances.show', $maintenance))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/ProjectMaintenances\\/Show', false);
    }

    #[Test]
    public function project_maintenance_store_and_quick_status_contracts_are_preserved(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        [$customer, $project] = $this->createProjectWithCustomer();

        $this->actingAs($admin)
            ->post(route('admin.project-maintenances.store'), [
                'project_id' => $project->id,
                'title' => 'New Maintenance Plan',
                'amount' => '199.99',
                'billing_cycle' => 'monthly',
                'start_date' => now()->toDateString(),
                'auto_invoice' => '1',
                'sales_rep_visible' => '0',
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.project-maintenances.index'))
            ->assertSessionHas('status');

        $maintenance = ProjectMaintenance::query()->where('title', 'New Maintenance Plan')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.project-maintenances.index'))
            ->patch(route('admin.project-maintenances.update', $maintenance), [
                'quick_status' => '1',
                'status' => 'paused',
            ])
            ->assertRedirect(route('admin.project-maintenances.index'))
            ->assertSessionHas('status', 'Maintenance status updated.');
    }

    #[Test]
    public function client_role_cannot_access_project_maintenance_admin_routes(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        $this->actingAs($client)
            ->get(route('admin.project-maintenances.index'))
            ->assertForbidden();
    }

    /**
     * @return array{0: Customer, 1: Project}
     */
    private function createProjectWithCustomer(): array
    {
        $customer = Customer::create([
            'name' => 'Parity Customer',
            'email' => 'parity-customer@example.test',
            'status' => 'active',
        ]);

        $project = Project::create([
            'customer_id' => $customer->id,
            'name' => 'Parity Project',
            'type' => 'software',
            'status' => 'ongoing',
        ]);

        return [$customer, $project];
    }
}
