<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientServicesUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function client_services_index_renders_inertia_page(): void
    {
        $customer = Customer::create(['name' => 'Service Customer']);
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($client)
            ->get(route('client.services.index'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Client\\/Services\\/Index', false);
    }

    #[Test]
    public function client_services_show_renders_inertia_for_owner(): void
    {
        $customer = Customer::create(['name' => 'Service Customer']);
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $product = Product::create([
            'name' => 'Hosting',
            'slug' => 'hosting',
            'description' => 'Hosting product',
            'status' => 'active',
        ]);

        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Starter',
            'slug' => 'starter',
            'interval' => 'monthly',
            'price' => 9.99,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'current_period_start' => now()->toDateString(),
            'current_period_end' => now()->addMonth()->toDateString(),
            'next_invoice_at' => now()->addMonth(),
            'auto_renew' => true,
        ]);

        $this->actingAs($client)
            ->get(route('client.services.show', $subscription))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Client\\/Services\\/Show', false);
    }

    #[Test]
    public function client_services_show_returns_not_found_for_other_customer(): void
    {
        $owner = Customer::create(['name' => 'Owner']);
        $other = Customer::create(['name' => 'Other']);

        $otherClient = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $other->id,
        ]);

        $product = Product::create([
            'name' => 'Domains',
            'slug' => 'domains',
            'description' => 'Domain service',
            'status' => 'active',
        ]);

        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Pro',
            'slug' => 'pro',
            'interval' => 'monthly',
            'price' => 19.99,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $subscription = Subscription::create([
            'customer_id' => $owner->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'current_period_start' => now()->toDateString(),
            'current_period_end' => now()->addMonth()->toDateString(),
            'next_invoice_at' => now()->addMonth(),
            'auto_renew' => true,
        ]);

        $this->actingAs($otherClient)
            ->get(route('client.services.show', $subscription))
            ->assertNotFound();
    }
}
