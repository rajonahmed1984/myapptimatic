<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminProjectCreateUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_project_create_renders_direct_inertia_component(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $response = $this->actingAs($admin)
            ->get(route('admin.projects.create'))
            ->assertOk();

        $this->assertContainsInertiaComponent($response->getContent(), 'Admin/Projects/Create');
    }

    #[Test]
    public function admin_project_store_validation_and_success_contracts_are_preserved(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::query()->create(['name' => 'Create Parity Customer']);
        $employee = Employee::query()->create([
            'name' => 'Create Parity Employee',
            'email' => 'create-parity-employee@example.test',
            'status' => 'active',
            'employment_type' => 'full_time',
        ]);

        $createUrl = route('admin.projects.create');

        $this->actingAs($admin)
            ->from($createUrl)
            ->post(route('admin.projects.store'), [
                'name' => '',
                'customer_id' => '',
                'type' => '',
                'status' => '',
                'total_budget' => '',
                'initial_payment_amount' => '',
                'currency' => '',
                'tasks' => [],
            ])
            ->assertRedirect($createUrl)
            ->assertSessionHasErrors([
                'name',
                'customer_id',
                'type',
                'status',
                'total_budget',
                'initial_payment_amount',
                'currency',
                'tasks',
            ]);

        $response = $this->actingAs($admin)
            ->from($createUrl)
            ->post(route('admin.projects.store'), [
                'name' => 'Created From UI Parity Test',
                'customer_id' => $customer->id,
                'type' => 'software',
                'status' => 'ongoing',
                'total_budget' => 5000,
                'initial_payment_amount' => 1000,
                'currency' => 'USD',
                'budget_amount' => 2000,
                'maintenances' => [[
                    'title' => '',
                    'amount' => '',
                    'billing_cycle' => 'monthly',
                    'start_date' => '',
                    'auto_invoice' => 1,
                    'sales_rep_visible' => 0,
                ]],
                'tasks' => [[
                    'title' => 'Initial setup',
                    'task_type' => 'feature',
                    'priority' => 'medium',
                    'start_date' => now()->toDateString(),
                    'due_date' => now()->addDay()->toDateString(),
                    'assignee' => 'employee:'.$employee->id,
                    'descriptions' => ['Kickoff'],
                    'customer_visible' => 0,
                ]],
            ]);

        $project = Project::query()->latest('id')->first();

        $response
            ->assertRedirect(route('admin.projects.show', $project))
            ->assertSessionHas('status', 'Project created with initial tasks and invoice.');

        $this->assertDatabaseCount('project_maintenances', 0);
    }

    #[Test]
    public function client_role_cannot_access_admin_project_create_route(): void
    {
        $client = User::factory()->create(['role' => Role::CLIENT]);

        $this->actingAs($client)
            ->get(route('admin.projects.create'))
            ->assertForbidden();
    }

    private function assertContainsInertiaComponent(string $content, string $component): void
    {
        $escaped = str_replace('/', '\\/', $component);

        $this->assertTrue(
            str_contains($content, $component) || str_contains($content, $escaped),
            "Response did not contain Inertia component [{$component}] in escaped or unescaped form."
        );
    }
}
