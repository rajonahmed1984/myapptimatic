<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCustomerInactiveDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_offers_delete_only_for_inactive_customers_without_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $active = Customer::create(['name' => 'Active', 'status' => 'active']);
        $inactive = Customer::create(['name' => 'Inactive', 'status' => 'inactive']);
        $inactiveWithInvoice = Customer::create(['name' => 'Inactive Billed', 'status' => 'inactive']);
        $this->invoiceFor($inactiveWithInvoice);

        $props = $this->actingAs($admin)
            ->get(route('admin.customers.index'))
            ->viewData('page')['props'];

        $destroyRoutes = collect($props['customers'])->pluck('routes.destroy', 'id');

        $this->assertNull($destroyRoutes[$active->id]);
        $this->assertNotNull($destroyRoutes[$inactive->id]);
        $this->assertNull($destroyRoutes[$inactiveWithInvoice->id]);
        $this->assertSame(1, $props['inactive_deletable_count']);
        $this->assertSame(route('admin.customers.destroy-inactive'), $props['routes']['destroy_inactive']);
    }

    public function test_delete_inactive_removes_only_inactive_customers_without_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $active = Customer::create(['name' => 'Active', 'status' => 'active']);
        $inactive = Customer::create(['name' => 'Inactive', 'status' => 'inactive']);
        $inactiveLogin = User::create([
            'name' => 'Inactive',
            'email' => 'inactive@example.com',
            'password' => 'secret123',
            'role' => Role::CLIENT,
            'customer_id' => $inactive->id,
        ]);
        $inactiveWithInvoice = Customer::create(['name' => 'Inactive Billed', 'status' => 'inactive']);
        $this->invoiceFor($inactiveWithInvoice);

        $this->actingAs($admin)
            ->delete(route('admin.customers.destroy-inactive'))
            ->assertRedirect(route('admin.customers.index'))
            ->assertSessionHas('status', 'Deleted 1 inactive customer. 1 kept because they have invoices, subscriptions, projects, or orders.');

        $this->assertDatabaseMissing('customers', ['id' => $inactive->id]);
        $this->assertDatabaseMissing('users', ['id' => $inactiveLogin->id]);
        $this->assertDatabaseHas('customers', ['id' => $active->id]);
        $this->assertDatabaseHas('customers', ['id' => $inactiveWithInvoice->id]);
    }

    public function test_delete_inactive_reports_when_nothing_can_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Customer::create(['name' => 'Active', 'status' => 'active']);

        $this->actingAs($admin)
            ->delete(route('admin.customers.destroy-inactive'))
            ->assertRedirect(route('admin.customers.index'))
            ->assertSessionHas('error');

        $this->assertSame(1, Customer::count());
    }

    public function test_client_cannot_delete_inactive_customers(): void
    {
        $customer = Customer::create(['name' => 'Inactive', 'status' => 'inactive']);
        $client = User::factory()->create(['role' => Role::CLIENT, 'customer_id' => $customer->id]);

        $this->actingAs($client)->delete(route('admin.customers.destroy-inactive'));

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    private function invoiceFor(Customer $customer): Invoice
    {
        return Invoice::create([
            'customer_id' => $customer->id,
            'number' => 'INV-INACTIVE-'.$customer->id,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 100,
            'late_fee' => 0,
            'total' => 100,
            'currency' => 'USD',
        ]);
    }
}
