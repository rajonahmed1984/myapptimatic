<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminCustomerTasksAiUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_customers_tasks_and_ai_pages_render_inertia_components(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $customer = Customer::create([
            'name' => 'Parity Customer',
            'status' => 'active',
            'email' => 'parity.customer@example.test',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Customers\\/Index', false);

        $this->actingAs($admin)
            ->get(route('admin.customers.create'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Customers\\/Form', false);

        $this->actingAs($admin)
            ->get(route('admin.customers.edit', $customer))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Customers\\/Form', false);

        $this->actingAs($admin)
            ->get(route('admin.tasks.index'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Tasks\\/Index', false);

        $this->actingAs($admin)
            ->get(route('admin.ai.business-status'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/AiBusinessStatus\\/Index', false);
    }
}
