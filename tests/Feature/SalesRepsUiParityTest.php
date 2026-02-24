<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\SalesRepresentative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SalesRepsUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sales_rep_index_create_and_edit_render_direct_inertia_components(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $salesRep = SalesRepresentative::create([
            'name' => 'Parity Rep',
            'email' => 'parity-rep@example.test',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.sales-reps.index'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/SalesReps\\/Index', false);

        $this->actingAs($admin)
            ->get(route('admin.sales-reps.create'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/SalesReps\\/Form', false);

        $this->actingAs($admin)
            ->get(route('admin.sales-reps.edit', $salesRep))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/SalesReps\\/Form', false);
    }

    #[Test]
    public function sales_rep_store_and_update_contracts_are_preserved(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.sales-reps.store'), [
                'name' => 'New Rep',
                'email' => 'new-rep@example.test',
                'phone' => '123456',
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.sales-reps.index'))
            ->assertSessionHas('status', 'Sales representative created.');

        $salesRep = SalesRepresentative::query()->where('email', 'new-rep@example.test')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.sales-reps.update', $salesRep), [
                'name' => 'Updated Rep',
                'email' => 'updated-rep@example.test',
                'phone' => '999999',
                'status' => 'inactive',
            ])
            ->assertRedirect(route('admin.sales-reps.edit', $salesRep))
            ->assertSessionHas('status', 'Sales representative updated.');
    }

    #[Test]
    public function client_role_cannot_access_sales_rep_admin_routes(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        $this->actingAs($client)
            ->get(route('admin.sales-reps.index'))
            ->assertForbidden();
    }
}
