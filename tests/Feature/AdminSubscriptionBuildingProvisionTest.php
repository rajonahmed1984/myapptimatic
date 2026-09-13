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

    public function test_per_flat_subscription_amount_is_recalculated_from_contracted_flats(): void
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
            'name' => 'Khan Heights Association',
            'company_name' => 'Khan Heights',
            'email' => 'khan@example.com',
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

        License::create([
            'subscription_id' => $subscription->id,
            'product_id' => $product->id,
            'license_key' => 'TESTKEY123456789012345678901299',
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
            // Stale amount left over from an earlier flat count.
            'subscription_amount' => 2000.00,
            'contracted_flats' => 34,
            'total_floors' => 10,
            'building_name' => 'Khan Heights',
            'building_address' => '270/ka, West Agargaon',
            'install_url' => 'https://app.mybuilding.com',
        ]);

        $response->assertRedirect(route('admin.subscriptions.edit', $subscription));

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'subscription_amount' => '1700.00',
        ]);
    }

    public function test_saving_building_details_resyncs_per_flat_subscription_amount(): void
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
            'name' => 'Khan Heights Association',
            'company_name' => 'Khan Heights',
            'email' => 'khan2@example.com',
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
            'license_key' => 'TESTKEY123456789012345678901288',
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.mybuilding.store'), [
            'license_id' => $license->id,
            'building_name' => 'Khan Heights',
            'building_address' => '270/ka, West Agargaon',
            'total_floors' => 10,
            'flats_per_floor' => 4,
            'floor_plan' => [4, 4, 4, 4, 4, 4, 3, 3, 2, 2],
            'install_url' => 'https://app.mybuilding.com',
            'owner_name' => 'Rajon Ahmed',
            'owner_email' => 'rajon@example.com',
            'owner_phone' => '01700000000',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('mybuilding_provisions', [
            'license_id' => $license->id,
            'contracted_flats' => 34,
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'subscription_amount' => '1700.00',
        ]);
    }

    public function test_install_url_edit_is_shown_back_when_the_customer_has_another_building(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $product = Product::create(['name' => 'MyBuilding', 'slug' => 'mybuilding', 'is_active' => true]);
        $plan = Plan::create([
            'product_id' => $product->id, 'name' => 'MyBuilding Monthly', 'slug' => 'mb-m',
            'interval' => 'monthly', 'price' => 50.00, 'pricing_model' => 'per_flat', 'is_active' => true,
        ]);
        $customer = Customer::create([
            'name' => 'Khan Heights Association', 'company_name' => 'Khan Heights',
            'email' => 'khan-repro@example.com', 'status' => 'active',
        ]);

        $makeSub = function (string $key) use ($customer, $plan, $product) {
            $sub = Subscription::create([
                'customer_id' => $customer->id, 'plan_id' => $plan->id,
                'subscription_amount' => 1700.00, 'status' => 'active',
                'start_date' => now()->toDateString(),
                'current_period_start' => now()->toDateString(),
                'current_period_end' => now()->addMonth()->toDateString(),
                'next_invoice_at' => now()->addMonth()->toDateString(),
            ]);
            $license = License::create([
                'subscription_id' => $sub->id, 'product_id' => $product->id,
                'license_key' => $key, 'status' => 'active',
                'starts_at' => now(), 'expires_at' => now()->addYear(),
            ]);

            return [$sub, $license];
        };

        // An older MyBuilding the same customer already runs.
        [$oldSub, $oldLicense] = $makeSub('OLDKEY1234567890123456789012345');
        MyBuildingProvision::create([
            'license_id' => $oldLicense->id, 'customer_id' => $customer->id,
            'building_name' => 'Old Tower', 'install_url' => 'http://127.0.0.1:8000',
            'total_floors' => 5, 'flats_per_floor' => 4, 'contracted_flats' => 20,
            'owner_name' => 'Owner', 'owner_email' => 'o@example.com', 'owner_phone' => '017',
            'status' => 'failed', 'last_error' => 'cURL error 7',
        ]);

        // The subscription being edited.
        [$sub, $license] = $makeSub('NEWKEY1234567890123456789012345');

        $this->actingAs($admin)->put(route('admin.subscriptions.update', $sub), [
            'customer_id' => $customer->id, 'plan_id' => $plan->id, 'status' => 'active',
            'start_date' => now()->format('d-m-Y'),
            'current_period_start' => now()->format('d-m-Y'),
            'current_period_end' => now()->addMonth()->format('d-m-Y'),
            'next_invoice_at' => now()->addMonth()->format('d-m-Y'),
            'contracted_flats' => 34, 'total_floors' => 10,
            'building_name' => 'Khan Heights', 'building_address' => '270/ka, West Agargaon',
            'install_url' => 'https://mybuildingbd.com',
        ])->assertRedirect();

        $this->assertDatabaseHas('mybuilding_provisions', [
            'license_id' => $license->id,
            'install_url' => 'https://mybuildingbd.com',
        ]);

        $this->actingAs($admin)->get(route('admin.subscriptions.edit', $sub))
            ->assertInertia(fn ($page) => $page
                ->where('form.fields.install_url', 'https://mybuildingbd.com')
                ->where('provision.install_url', 'https://mybuildingbd.com')
            );
    }

    public function test_approving_building_order_auto_provisions_building_in_mybuilding(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://app.mybuilding.com/api/v1/external/register-building' => \Illuminate\Support\Facades\Http::response([
                'status' => 'success',
                'data' => [
                    'building_id' => 99,
                    'client_account_id' => 101,
                    'registration_code' => 'REG-999',
                    'flats_created' => 40,
                ],
            ], 200),
        ]);

        config(['mybuilding.provision_secret' => 'test-secret-key']);

        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::create(['name' => 'MyBuilding', 'slug' => 'mybuilding', 'is_active' => true]);
        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Building & Flat-wise Plan',
            'slug' => 'building-flat-wise',
            'interval' => 'monthly',
            'price' => 50.00,
            'pricing_model' => 'per_flat',
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'name' => 'Auto Building Owner',
            'company_name' => 'Sunrise Tower',
            'email' => 'owner@sunrisetower.com',
            'status' => 'active',
        ]);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'subscription_amount' => 2000.00,
            'status' => 'pending',
            'start_date' => now()->toDateString(),
            'current_period_start' => now()->toDateString(),
            'current_period_end' => now()->addMonth()->toDateString(),
            'next_invoice_at' => now()->addMonth()->toDateString(),
        ]);

        $license = License::create([
            'subscription_id' => $subscription->id,
            'product_id' => $product->id,
            'license_key' => 'AUTOKEY123456789012345678901234',
            'status' => 'pending',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $order = \App\Models\Order::create([
            'order_number' => \App\Models\Order::nextNumber(),
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'subscription_id' => $subscription->id,
            'status' => 'pending',
        ]);

        $provision = MyBuildingProvision::create([
            'license_id' => $license->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'building_name' => 'Sunrise Tower',
            'building_address' => 'Mirpur-10, Dhaka',
            'total_floors' => 10,
            'flats_per_floor' => 4,
            'contracted_flats' => 40,
            'install_url' => 'https://app.mybuilding.com',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@sunrisetower.com',
            'owner_phone' => '01711111111',
            'status' => MyBuildingProvision::STATUS_PENDING,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.orders.approve', $order), [
            'license_key' => 'AUTOKEY123456789012345678901234',
            'license_url' => 'https://app.mybuilding.com',
        ]);

        $response->assertRedirect();
        $this->assertEquals('accepted', $order->fresh()->status);
        $this->assertEquals('active', $subscription->fresh()->status);

        $freshProvision = $provision->fresh();
        $this->assertEquals(MyBuildingProvision::STATUS_PROVISIONED, $freshProvision->status);
        $this->assertEquals(99, $freshProvision->remote_building_id);
        $this->assertEquals('REG-999', $freshProvision->registration_code);
        $this->assertNotNull($freshProvision->provisioned_at);

        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/v1/external/register-building')
                && $request['building_name'] === 'Sunrise Tower'
                && $request['license_key'] === 'AUTOKEY123456789012345678901234';
        });
    }

    public function test_approving_order_without_pre_existing_provision_auto_creates_and_provisions(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://demo.mybuilding.com/api/v1/external/register-building' => \Illuminate\Support\Facades\Http::response([
                'status' => 'success',
                'data' => [
                    'building_id' => 120,
                    'client_account_id' => 130,
                    'registration_code' => 'REG-AUTO-CREATED',
                    'flats_created' => 40,
                ],
            ], 200),
        ]);

        config(['mybuilding.provision_secret' => 'test-secret-key']);

        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::create(['name' => 'MyBuilding', 'slug' => 'mybuilding', 'is_active' => true]);
        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Building & Flat-wise Plan 2',
            'slug' => 'building-flat-wise-2',
            'interval' => 'monthly',
            'price' => 50.00,
            'pricing_model' => 'per_flat',
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'name' => 'Auto Provision Customer',
            'company_name' => 'Crescent Plaza',
            'email' => 'crescent@example.com',
            'status' => 'active',
        ]);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'subscription_amount' => 2000.00,
            'status' => 'pending',
            'start_date' => now()->toDateString(),
            'current_period_start' => now()->toDateString(),
            'current_period_end' => now()->addMonth()->toDateString(),
            'next_invoice_at' => now()->addMonth()->toDateString(),
        ]);

        $license = License::create([
            'subscription_id' => $subscription->id,
            'product_id' => $product->id,
            'license_key' => 'AUTOCREATEKEY123456789012345678',
            'status' => 'pending',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $order = \App\Models\Order::create([
            'order_number' => \App\Models\Order::nextNumber(),
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'subscription_id' => $subscription->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.orders.approve', $order), [
            'license_key' => 'AUTOCREATEKEY123456789012345678',
            'license_url' => 'https://demo.mybuilding.com',
        ]);

        $response->assertRedirect();
        $this->assertEquals('accepted', $order->fresh()->status);

        $provision = MyBuildingProvision::where('license_id', $license->id)->first();
        $this->assertNotNull($provision);
        $this->assertEquals(MyBuildingProvision::STATUS_PROVISIONED, $provision->status);
        $this->assertEquals(120, $provision->remote_building_id);
        $this->assertEquals('REG-AUTO-CREATED', $provision->registration_code);
    }
}
