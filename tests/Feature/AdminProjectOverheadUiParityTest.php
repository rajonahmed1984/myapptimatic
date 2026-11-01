<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectOverhead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
