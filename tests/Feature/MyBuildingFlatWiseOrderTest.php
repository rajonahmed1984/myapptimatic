<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\MyBuildingProvision;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyBuildingFlatWiseOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_orders_index_exposes_flat_wise_pricing(): void
    {
        $customer = Customer::create(['name' => 'Test Customer', 'email' => 'client@example.com']);
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $product = Product::create([
            'name' => 'MyBuilding',
            'slug' => 'mybuilding',
            'status' => 'active',
        ]);

        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'MyBuilding Monthly',
            'slug' => 'mybuilding-monthly',
            'interval' => 'monthly',
            'price' => 50.00,
            'pricing_model' => 'per_flat',
            'currency' => 'BDT',
            'is_active' => true,
        ]);

        $response = $this->actingAs($client)->get(route('client.orders.index'));
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $this->assertTrue($props['has_customer']);

        $productData = collect($props['products'])->firstWhere('slug', 'mybuilding');
        $this->assertNotNull($productData);
        $planData = collect($productData['plans'])->firstWhere('id', $plan->id);
        $this->assertNotNull($planData);
        $this->assertSame('per_flat', $planData['pricing_model']);
        $this->assertTrue($planData['is_per_flat']);
        $this->assertEquals(50.00, $planData['price']);
    }

    public function test_client_can_place_order_with_floor_wise_flats(): void
    {
        $customer = Customer::create([
            'name' => 'Building Owner',
            'email' => 'owner@example.com',
            'phone' => '01700000000',
        ]);
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $product = Product::create([
            'name' => 'MyBuilding',
            'slug' => 'mybuilding',
            'status' => 'active',
        ]);

        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'MyBuilding Monthly',
            'slug' => 'mybuilding-monthly',
            'interval' => 'monthly',
            'price' => 50.00,
            'pricing_model' => 'per_flat',
            'currency' => 'BDT',
            'is_active' => true,
        ]);

        // User submits: 10 floors, GF = 2, 1st = 4, 2nd = 3 (remaining 7 floors = 0)
        // Total flats = 2 + 4 + 3 = 9 flats.
        $floorPlan = [2, 4, 3, 0, 0, 0, 0, 0, 0, 0];

        $response = $this->actingAs($client)->post(route('client.orders.store'), [
            'plan_id' => $plan->id,
            'building_name' => 'Skyline Heights',
            'building_number' => 'Plot #12',
            'building_address' => 'Road #5, Dhanmondi',
            'total_floors' => 10,
            'has_ground_floor' => 1,
            'floor_plan' => $floorPlan,
        ]);

        $response->assertRedirect();

        // 9 flats * 50.00 = 450.00 base monthly recurring amount
        $expectedMonthly = 9 * 50.00;

        $subscription = Subscription::where('customer_id', $customer->id)->first();
        $this->assertNotNull($subscription);
        $this->assertEquals($expectedMonthly, (float) $subscription->subscription_amount);

        $provision = MyBuildingProvision::where('customer_id', $customer->id)->first();
        $this->assertNotNull($provision);
        $this->assertSame('Skyline Heights', $provision->building_name);
        $this->assertSame(10, $provision->total_floors);
        $this->assertSame(9, $provision->contracted_flats);
        $this->assertEquals($floorPlan, $provision->floor_plan);
    }

    public function test_admin_can_create_per_flat_plan(): void
    {
        $admin = User::factory()->create([
            'role' => Role::ADMIN,
        ]);

        $product = Product::create([
            'name' => 'MyBuilding',
            'slug' => 'mybuilding',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.plans.store'), [
            'product_id' => $product->id,
            'name' => 'Custom Flat Plan',
            'slug' => 'custom-flat-plan',
            'pricing_model' => 'per_flat',
            'is_active' => 1,
            'pricing_rows' => [
                ['interval' => 'monthly', 'price' => 50.00],
            ],
        ]);

        $response->assertRedirect(route('admin.plans.index'));

        $plan = Plan::where('slug', 'custom-flat-plan')->first();
        $this->assertNotNull($plan);
        $this->assertSame('per_flat', $plan->pricing_model);
        $this->assertEquals(50.00, (float) $plan->price);
        $this->assertTrue($plan->isPerFlat());
    }
}

