<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectOverhead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminProjectOverheadUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_project_overhead_index_renders_direct_inertia_component(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $customer = Customer::create([
            'name' => 'Overhead UI Customer',
            'email' => 'overhead-ui@example.test',
            'status' => 'active',
        ]);

        $project = Project::create([
            'name' => 'Overhead UI Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'currency' => 'USD',
        ]);

        ProjectOverhead::create([
            'project_id' => $project->id,
            'short_details' => 'Setup Fee',
            'amount' => 120,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.projects.overheads.index', $project))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Projects\\/Overheads\\/Index', false);
    }

    #[Test]
    public function project_overhead_store_and_destroy_contracts_are_preserved(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $customer = Customer::create([
            'name' => 'Overhead Contract Customer',
            'email' => 'overhead-contract@example.test',
            'status' => 'active',
        ]);

        $project = Project::create([
            'name' => 'Overhead Contract Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'currency' => 'USD',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.projects.overheads.store', $project), [
                'short_details' => 'Initial Overhead',
                'amount' => '99.99',
            ])
            ->assertRedirect(route('admin.projects.overheads.index', $project))
            ->assertSessionHas('success', 'Overhead added successfully.');

        $overhead = ProjectOverhead::query()->where('project_id', $project->id)->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('admin.projects.overheads.destroy', [$project, $overhead]))
            ->assertRedirect(route('admin.projects.overheads.index', $project))
            ->assertSessionHas('success', 'Overhead deleted successfully.');
    }

    #[Test]
    public function an_admin_can_generate_an_invoice_for_one_project_overhead(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create([
            'name' => 'Single Overhead Invoice Customer',
            'email' => 'single-overhead-invoice@example.test',
            'status' => 'active',
        ]);
        $project = Project::create([
            'name' => 'Single Overhead Invoice Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'currency' => 'USD',
        ]);
        $overhead = ProjectOverhead::create([
            'project_id' => $project->id,
            'short_details' => 'Dedicated setup fee',
            'amount' => 120,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.projects.overheads.invoice-one', [$project, $overhead]))
            ->assertRedirect(route('admin.projects.show', $project))
            ->assertSessionHas('success', 'Invoice created for overhead fee.');

        $overhead->refresh();
        $this->assertNotNull($overhead->invoice_id);
        $this->assertDatabaseHas('invoices', [
            'id' => $overhead->invoice_id,
            'project_id' => $project->id,
            'type' => 'project_overhead',
            'subtotal' => 120,
        ]);
        $this->assertSame(1, Invoice::query()->where('project_id', $project->id)->count());

        $this->actingAs($admin)
            ->post(route('admin.projects.overheads.invoice-one', [$project, $overhead]))
            ->assertSessionHas('success', 'This overhead fee already has an invoice.');

        $this->assertSame(1, Invoice::query()->where('project_id', $project->id)->count());
    }

    #[Test]
    public function an_overhead_pointing_at_a_missing_invoice_can_be_invoiced_again(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create([
            'name' => 'Missing Invoice Customer',
            'email' => 'missing-invoice@example.test',
            'status' => 'active',
        ]);
        $project = Project::create([
            'name' => 'Missing Invoice Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'currency' => 'USD',
        ]);
        $overhead = ProjectOverhead::create([
            'project_id' => $project->id,
            'short_details' => 'Orphaned fee',
            'amount' => 75,
        ]);

        // Foreign keys cannot be switched off inside the test transaction, only deferred.
        DB::statement('PRAGMA defer_foreign_keys = ON');
        $overhead->forceFill(['invoice_id' => 999999])->save();

        $this->actingAs($admin)
            ->post(route('admin.projects.overheads.invoice-one', [$project, $overhead]))
            ->assertSessionHas('success', 'Invoice created for overhead fee.');

        $overhead->refresh();
        $this->assertNotSame(999999, (int) $overhead->invoice_id);
        $this->assertNotNull($overhead->invoice);

        $this->actingAs($admin)
            ->get(route('admin.projects.show', $project))
            ->assertInertia(fn ($page) => $page->where('project.overheads.0.status_label', 'Unpaid'));
    }

    #[Test]
    public function an_overhead_already_billed_through_the_budget_is_not_invoiced_twice(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create(['name' => 'Double Bill Customer', 'status' => 'active']);
        $project = Project::create([
            'name' => 'Double Bill Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 200,
            'currency' => 'USD',
        ]);
        $overhead = ProjectOverhead::create([
            'project_id' => $project->id,
            'short_details' => 'Paid through remaining budget',
            'amount' => 30,
        ]);
        Invoice::create([
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'number' => 'BILL-1',
            'status' => 'paid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'subtotal' => 230,
            'late_fee' => 0,
            'total' => 230,
            'currency' => 'USD',
            'type' => 'project_remaining_budget',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.projects.overheads.invoice-one', [$project, $overhead]))
            ->assertSessionHas('error');

        $this->assertNull($overhead->fresh()->invoice_id);
        $this->assertSame(1, Invoice::query()->where('project_id', $project->id)->count());
    }

    #[Test]
    public function customer_project_due_counts_paid_overhead_invoices_and_ignores_vat(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create(['name' => 'Customer Due', 'status' => 'active']);
        $project = Project::create([
            'name' => 'Customer Due Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 100,
            'currency' => 'USD',
        ]);
        ProjectOverhead::create(['project_id' => $project->id, 'short_details' => 'Fee', 'amount' => 25]);

        foreach ([['project_remaining_budget', 100, 110], ['project_overhead', 25, 25]] as $index => [$type, $subtotal, $total]) {
            Invoice::create([
                'customer_id' => $customer->id,
                'project_id' => $project->id,
                'number' => 'DUE-'.$index,
                'status' => 'paid',
                'issue_date' => now()->toDateString(),
                'due_date' => now()->toDateString(),
                'subtotal' => $subtotal,
                'late_fee' => 0,
                'total' => $total,
                'currency' => 'USD',
                'type' => $type,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.customers.show', ['customer' => $customer, 'tab' => 'projects']))
            ->assertInertia(fn ($page) => $page
                ->where('projects.0.paid_amount', 125)
                ->where('projects.0.due_amount', 0));
    }

    #[Test]
    public function deleting_an_invoice_clears_the_overhead_link(): void
    {
        $customer = Customer::create(['name' => 'Delete Link Customer', 'status' => 'active']);
        $project = Project::create([
            'name' => 'Delete Link Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'currency' => 'USD',
        ]);
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'number' => 'DEL-1',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'subtotal' => 50,
            'late_fee' => 0,
            'total' => 50,
            'currency' => 'USD',
            'type' => 'project_overhead',
        ]);
        $overhead = ProjectOverhead::create([
            'project_id' => $project->id,
            'short_details' => 'Linked fee',
            'amount' => 50,
            'invoice_id' => $invoice->id,
        ]);

        $invoice->delete();

        $this->assertNull($overhead->fresh()->invoice_id);
    }
}
