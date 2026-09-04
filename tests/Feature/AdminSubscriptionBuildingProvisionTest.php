<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\License;
use App\Models\MyBuildingProvision;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSubscriptionBuildingProvisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_edit_page_with_per_flat_subscription_props(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $product = Product::create([
            'name' => 'MyBuilding',
            'slug' => 'mybuilding',
            'is_active' => true,
        ]);

        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'MyBuilding Monthly',
            'slug' => 'mybuilding-monthly',
            'interval' => 'monthly',
            'price' => 50.00,
            'pricing_model' => 'per_flat',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'Gulshan Tower Association',
            'company_name' => 'Gulshan Tower',
            'email' => 'gulshan@example.com',
            'status' => 'active',
        ]);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'subscription_amount' => 2000.00,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'current_period_start' => now()->toDateString(),
            'current_period_end' => now()->addMonth()->toDateString(),
            'next_invoice_at' => now()->addMonth()->toDateString(),
        ]);

        $license = License::create([
            'subscription_id' => $subscription->id,
            'product_id' => $product->id,
            'license_key' => 'TESTKEY123456789012345678901234',
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.subscriptions.edit', $subscription));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Subscriptions/Form')
            ->where('is_edit', true)
            ->where('form.fields.contracted_flats', '40')
            ->has('plans')
        );
    }

    public function test_admin_can_update_building_and_contracted_flats_and_sync_provision(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $product = Product::create([
            'name' => 'MyBuilding',
            'slug' => 'mybuilding',
            'is_active' => true,
        ]);

        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'MyBuilding Monthly',
            'slug' => 'mybuilding-monthly',
            'interval' => 'monthly',
            'price' => 50.00,
            'pricing_model' => 'per_flat',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'Gulshan Tower Association',
            'company_name' => 'Gulshan Tower',
            'email' => 'gulshan2@example.com',
            'status' => 'active',
        ]);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'subscription_amount' => 2000.00,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'current_period_start' => now()->toDateString(),
            'current_period_end' => now()->addMonth()->toDateString(),
            'next_invoice_at' => now()->addMonth()->toDateString(),
        ]);

        $license = License::create([
            'subscription_id' => $subscription->id,
            'product_id' => $product->id,
            'license_key' => 'TESTKEY123456789012345678901234',
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $response = $this->actingAs($admin)->put(route('admin.subscriptions.update', $subscription), [
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->format('d-m-Y'),
            'current_period_start' => now()->format('d-m-Y'),
            'current_period_end' => now()->addMonth()->format('d-m-Y'),
            'next_invoice_at' => now()->addMonth()->format('d-m-Y'),
            'subscription_amount' => 2500.00,
            'contracted_flats' => 50,
            'total_floors' => 12,
            'building_name' => 'Gulshan Tower Deluxe',
            'building_address' => 'Road 11, Gulshan, Dhaka',
            'install_url' => 'https://app.mybuilding.com',
        ]);

        $response->assertRedirect(route('admin.subscriptions.edit', $subscription));

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'subscription_amount' => '2500.00',
        ]);

        $this->assertDatabaseHas('mybuilding_provisions', [
            'license_id' => $license->id,
            'customer_id' => $customer->id,
            'building_name' => 'Gulshan Tower Deluxe',
            'building_address' => 'Road 11, Gulshan, Dhaka',
            'total_floors' => 12,
            'contracted_flats' => 50,
            'flats_per_floor' => 5,
        ]);
    }
}
