<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\SalesRepresentative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProjectOverheadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_store_overhead_and_invoice_includes_it(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $customer = Customer::create([
            'name' => 'Overhead Co',
            'email' => 'overhead@example.com',
            'status' => 'active',
        ]);

        $salesRep = SalesRepresentative::create([
            'name' => 'Sales Overhead',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.projects.store'), [
            'name' => 'Overhead Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1200,
            'initial_payment_amount' => 500,
            'currency' => 'USD',
            'software_overhead' => 80,
            'website_overhead' => 20,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'tasks' => [
                [
                    'title' => 'Overhead Task',
                    'task_type' => 'feature',
                    'priority' => 'medium',
                    'start_date' => now()->toDateString(),
                    'due_date' => now()->addDay()->toDateString(),
                    'assignee' => 'sales_rep:'.$salesRep->id,
                    'descriptions' => ['Overhead work'],
                ],
            ],
        ]);

        $response->assertRedirect();

        $project = Project::latest('id')->first();
        $this->assertNotNull($project);
        $this->assertSame(80.0, (float) $project->software_overhead);
        $this->assertSame(20.0, (float) $project->website_overhead);
        $this->assertSame(100.0, $project->overhead_total);

        $invoice = Invoice::where('project_id', $project->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame(600.0, (float) $invoice->total);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => sprintf('Software overhead for project %s', $project->name),
            'line_total' => 80,
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => sprintf('Website overhead for project %s', $project->name),
            'line_total' => 20,
        ]);
    }
}
