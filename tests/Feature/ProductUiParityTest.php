<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function products_index_is_inertia_when_legacy_flag_is_off(): void
    {
        config()->set('features.admin_products_index', false);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.products.index'));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Admin\\/Products\\/Index', false);
    }

    #[Test]
    public function products_index_remains_inertia_when_legacy_flag_is_on(): void
    {
        config()->set('features.admin_products_index', true);

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.products.index'));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Admin\\/Products\\/Index', false);
    }

    #[Test]
    public function products_index_permission_guard_remains_forbidden_for_client_role_with_or_without_legacy_flag(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        config()->set('features.admin_products_index', false);
        $this->actingAs($client)
            ->get(route('admin.products.index'))
            ->assertForbidden();

        config()->set('features.admin_products_index', true);
        $this->actingAs($client)
            ->get(route('admin.products.index'))
            ->assertForbidden();
    }

    #[Test]
    public function product_show_page_renders_inertia_component_with_client_services(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $product = \App\Models\Product::create([
            'name' => 'Carrot Host Cloud',
            'slug' => 'carrot-host-cloud',
            'status' => 'active',
            'description' => 'Managed cloud hosting product',
        ]);

        $plan = \App\Models\Plan::create([
            'product_id' => $product->id,
            'name' => 'Pro Monthly',
            'slug' => 'pro-monthly',
            'price' => 49.00,
            'currency' => 'USD',
            'interval' => 'monthly',
            'is_active' => true,
        ]);

        $customer = \App\Models\Customer::create([
            'name' => 'John Doe',
            'company_name' => 'Acme Corp',
            'email' => 'john@acme.test',
            'status' => 'active',
        ]);

        $subscription = \App\Models\Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'subscription_amount' => 49.00,
            'start_date' => now()->toDateString(),
            'current_period_start' => now()->toDateString(),
            'current_period_end' => now()->addMonth()->toDateString(),
            'next_invoice_at' => now()->addMonth()->toDateString(),
        ]);

        $license = \App\Models\License::create([
            'subscription_id' => $subscription->id,
            'product_id' => $product->id,
            'license_key' => 'TEST-KEY-ABC-1234',
            'status' => 'active',
            'starts_at' => now()->toDateString(),
            'expires_at' => now()->addYear()->toDateString(),
            'max_domains' => 5,
        ]);

        \App\Models\LicenseDomain::create([
            'license_id' => $license->id,
            'domain' => 'acme.test',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.products.show', $product));

        $response->assertOk();
        $response->assertSee('data-page=', false);
        $response->assertSee('Admin\\/Products\\/Show', false);
        $response->assertSee('Carrot Host Cloud');
        $response->assertSee('John Doe');
        $response->assertSee('Acme Corp');
        $response->assertSee('TEST-KEY-ABC-1234');
    }
}
