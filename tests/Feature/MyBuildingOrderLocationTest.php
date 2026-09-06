<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\MyBuildingProvision;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use App\Support\BangladeshLocations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyBuildingOrderLocationTest extends TestCase
{
    use RefreshDatabase;

    private function seedLocations(): District
    {
        BangladeshLocations::forget();

        $district = District::create([
            'slug' => 'cumilla',
            'name' => 'Cumilla',
            'bn_name' => 'কুমিল্লা',
            'division' => 'Chattogram',
        ]);

        City::create([
            'district_id' => $district->id,
            'slug' => 'cumilla-sadar',
            'name' => 'Cumilla Sadar',
        ]);

        return $district;
    }

    private function makePlan(): Plan
    {
        $product = Product::create([
            'name' => 'MyBuilding',
            'slug' => 'mybuilding',
            'status' => 'active',
        ]);

        return Plan::create([
            'product_id' => $product->id,
            'name' => 'MyBuilding Monthly',
            'slug' => 'mybuilding-monthly',
            'interval' => 'monthly',
            'price' => 50.00,
            'pricing_model' => 'per_flat',
            'currency' => 'BDT',
            'is_active' => true,
        ]);
    }

    public function test_review_page_offers_local_districts_and_cities(): void
    {
        $district = $this->seedLocations();
        $plan = $this->makePlan();

        $customer = Customer::create(['name' => 'Owner', 'email' => 'owner@example.com']);
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $response = $this->actingAs($client)->get(route('client.orders.review', ['plan_id' => $plan->id]));
        $response->assertOk();

        $locations = $response->viewData('page')['props']['locations'];
        $this->assertCount(1, $locations);
        $this->assertSame($district->id, $locations[0]['id']);
        $this->assertSame('cumilla', $locations[0]['slug']);
        $this->assertSame('Cumilla Sadar', $locations[0]['cities'][0]['name']);
    }

    public function test_order_stores_the_chosen_location_by_slug_and_name(): void
    {
        $district = $this->seedLocations();
        $city = $district->cities()->first();
        $plan = $this->makePlan();

        $customer = Customer::create([
            'name' => 'Building Owner',
            'email' => 'owner@example.com',
            'phone' => '01700000000',
        ]);
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $response = $this->actingAs($client)->post(route('client.orders.store'), [
            'plan_id' => $plan->id,
            'building_name' => 'Skyline Heights',
            'total_floors' => 3,
            'has_ground_floor' => 1,
            'floor_plan' => [2, 4, 4],
            'district_id' => $district->id,
            'city_id' => $city->id,
            'area_name' => 'Kandirpar',
        ]);

        $response->assertRedirect();

        $provision = MyBuildingProvision::where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame($district->id, $provision->district_id);
        $this->assertSame('cumilla', $provision->district_slug);
        $this->assertSame('Cumilla', $provision->district_name);
        $this->assertSame($city->id, $provision->city_id);
        $this->assertSame('cumilla-sadar', $provision->city_slug);
        $this->assertSame('Cumilla Sadar', $provision->city_name);
        $this->assertSame('Kandirpar', $provision->area_name);
        $this->assertSame(10, $provision->contracted_flats);
    }

    public function test_city_from_another_district_is_not_recorded(): void
    {
        $district = $this->seedLocations();
        $other = District::create(['slug' => 'dhaka', 'name' => 'Dhaka']);
        $otherCity = City::create([
            'district_id' => $other->id,
            'slug' => 'savar',
            'name' => 'Savar',
        ]);
        $plan = $this->makePlan();

        $customer = Customer::create(['name' => 'Owner', 'email' => 'owner@example.com']);
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($client)->post(route('client.orders.store'), [
            'plan_id' => $plan->id,
            'building_name' => 'Mismatch Tower',
            'total_floors' => 2,
            'floor_plan' => [2, 2],
            'district_id' => $district->id,
            'city_id' => $otherCity->id,
        ])->assertRedirect();

        $provision = MyBuildingProvision::where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame('cumilla', $provision->district_slug);
        $this->assertNull($provision->city_id);
        $this->assertNull($provision->city_slug);
    }
}
