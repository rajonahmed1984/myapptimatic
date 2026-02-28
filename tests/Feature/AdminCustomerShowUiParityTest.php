<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectMaintenance;
use App\Models\ProjectTask;
use App\Models\ProjectTaskSubtask;
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
            ->assertSee('Admin\\/Customers\\/Show', false)
            ->assertSee('&quot;tab&quot;:&quot;summary&quot;', false)
            ->assertDontSee('&quot;tab&quot;:&quot;unknown&quot;', false);
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
    public function customer_projects_tab_exposes_task_summary_and_maintenance_contract(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create([
            'name' => 'Projects Summary Customer',
            'email' => 'projects-summary-customer@example.test',
            'status' => 'active',
        ]);
        $project = Project::create([
            'customer_id' => $customer->id,
            'name' => 'Projects Summary Target',
        ]);

        $pendingTask = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Pending Task',
            'status' => 'pending',
            'customer_visible' => true,
        ]);
        $completedTask = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Completed Task',
            'status' => 'completed',
            'customer_visible' => true,
        ]);

        ProjectTaskSubtask::create([
            'project_task_id' => $pendingTask->id,
            'title' => 'Pending Subtask',
            'is_completed' => false,
        ]);
        ProjectTaskSubtask::create([
            'project_task_id' => $completedTask->id,
            'title' => 'Done Subtask',
            'is_completed' => true,
        ]);

        ProjectMaintenance::create([
            'project_id' => $project->id,
            'customer_id' => $customer->id,
            'title' => 'Monthly Maintenance',
            'amount' => 1000,
            'currency' => 'BDT',
            'billing_cycle' => 'monthly',
            'start_date' => now()->toDateString(),
            'next_billing_date' => now()->addMonth()->toDateString(),
            'status' => 'active',
            'auto_invoice' => true,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.customers.show', ['customer' => $customer, 'tab' => 'projects']))
            ->assertOk();

        $payload = $this->inertiaPayload($response->getContent());

        $this->assertSame('projects', data_get($payload, 'props.tab'));
        $this->assertSame(2, data_get($payload, 'props.project_task_summary.total'));
        $this->assertSame(1, data_get($payload, 'props.project_task_summary.pending'));
        $this->assertSame(1, data_get($payload, 'props.project_task_summary.completed'));
        $this->assertSame(2, data_get($payload, 'props.project_subtask_summary.total'));
        $this->assertSame(1, data_get($payload, 'props.project_subtask_summary.completed'));
        $this->assertSame(50, data_get($payload, 'props.project_task_progress.percent'));
        $this->assertCount(1, data_get($payload, 'props.project_maintenances', []));
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

    /**
     * @return array<string, mixed>
     */
    private function inertiaPayload(string $html): array
    {
        preg_match('/data-page="([^"]+)"/', $html, $matches);
        $this->assertArrayHasKey(1, $matches, 'Inertia payload is missing in response.');

        $decoded = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        $payload = json_decode($decoded, true);
        $this->assertIsArray($payload);

        return $payload;
    }
}
