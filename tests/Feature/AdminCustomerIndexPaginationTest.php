<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminCustomerIndexPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_cover_every_customer_once_even_with_identical_created_at(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sameSecond = Carbon::parse('2026-09-01 10:00:00');

        for ($i = 1; $i <= 65; $i++) {
            $customer = Customer::create(['name' => "Customer {$i}", 'status' => 'active']);
            $customer->forceFill(['created_at' => $sameSecond, 'updated_at' => $sameSecond])->save();
        }

        $seen = [];
        for ($page = 1; $page <= 3; $page++) {
            $response = $this->actingAs($admin)->get(route('admin.customers.index', ['page' => $page]));
            $props = $response->viewData('page')['props'];

            $this->assertSame(65, $props['pagination']['total']);
            $this->assertSame(3, $props['pagination']['last_page']);
            $this->assertSame($page, $props['pagination']['current_page']);

            foreach ($props['customers'] as $row) {
                $seen[] = $row['id'];
            }
        }

        $this->assertCount(65, $seen);
        $this->assertCount(65, array_unique($seen));
    }

    public function test_pagination_keeps_search_query(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Customer::create(['name' => 'Alpha', 'status' => 'active']);

        $props = $this->actingAs($admin)
            ->get(route('admin.customers.index', ['search' => 'Alp']))
            ->viewData('page')['props'];

        $this->assertSame(['search' => 'Alp'], $props['pagination']['query']);
        $this->assertSame(1, $props['pagination']['total']);
    }
}
