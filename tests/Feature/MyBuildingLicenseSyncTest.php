<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\MyBuildingProvision;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MyBuildingLicenseSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MyBuildingLicenseSyncTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'shared-test-secret';

    private const INSTALL = 'https://install.mybuilding.test';

    private Customer $customer;

    private Plan $plan;

    private Subscription $subscription;

    private License $license;

    private MyBuildingProvision $provision;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mybuilding.provision_secret' => self::SECRET]);
        Http::fake([self::INSTALL.'/*' => Http::response(['success' => true], 200)]);

        $product = Product::create(['name' => 'MyBuilding', 'slug' => 'mybuilding', 'is_active' => true]);
        $this->plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'MyBuilding Monthly',
            'slug' => 'mybuilding-monthly',
            'interval' => 'monthly',
            'price' => 50.00,
            'pricing_model' => 'per_flat',
            'is_active' => true,
        ]);
        $this->customer = Customer::create(['name' => 'Tower Assoc', 'email' => 'tower@example.com', 'status' => 'active']);
        $this->subscription = Subscription::create([
            'customer_id' => $this->customer->id,
            'plan_id' => $this->plan->id,
            'subscription_amount' => 2000.00,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'current_period_start' => now()->toDateString(),
            'current_period_end' => now()->addMonth()->toDateString(),
            'next_invoice_at' => now()->addMonth()->toDateString(),
        ]);
        $this->license = License::create([
            'subscription_id' => $this->subscription->id,
            'product_id' => $product->id,
            'license_key' => 'MBKEY0000000000000000000000000001',
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);
        $this->provision = MyBuildingProvision::create([
            'license_id' => $this->license->id,
            'customer_id' => $this->customer->id,
            'building_name' => 'Tower',
            'total_floors' => 10,
            'flats_per_floor' => 4,
            'contracted_flats' => 40,
            'install_url' => self::INSTALL,
            'owner_name' => 'Owner',
            'owner_email' => 'owner@example.com',
            'owner_phone' => '01700000000',
            'status' => MyBuildingProvision::STATUS_PROVISIONED,
        ]);
    }

    private function pushedStatuses(): array
    {
        return collect(Http::recorded())
            ->map(fn ($pair) => $pair[0])
            ->filter(fn (HttpRequest $request) => str_ends_with($request->url(), '/api/v1/external/subscription'))
            ->map(fn (HttpRequest $request) => $request->data())
            ->values()
            ->all();
    }

    public function test_admin_suspending_subscription_pushes_suspension_to_installation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('admin.subscriptions.update', $this->subscription), [
            'customer_id' => $this->customer->id,
            'plan_id' => $this->plan->id,
            'status' => 'suspended',
            'start_date' => now()->format('d-m-Y'),
            'current_period_start' => now()->format('d-m-Y'),
            'current_period_end' => now()->addMonth()->format('d-m-Y'),
            'next_invoice_at' => now()->addMonth()->format('d-m-Y'),
            'contracted_flats' => 40,
            'total_floors' => 10,
            'install_url' => self::INSTALL,
        ])->assertRedirect();

        $pushes = $this->pushedStatuses();

        $this->assertCount(1, $pushes, 'one request should send one final state');
        $this->assertSame('suspended', $pushes[0]['subscription_status']);
        $this->assertSame($this->license->license_key, $pushes[0]['license_key']);

        // The call is signed the way the installation verifies it.
        Http::assertSent(function (HttpRequest $request) {
            $timestamp = $request->header('X-Apptimatic-Timestamp')[0] ?? '';

            return hash_equals(
                hash_hmac('sha256', $timestamp.'.'.$request->body(), self::SECRET),
                $request->header('X-Apptimatic-Signature')[0] ?? ''
            );
        });
    }

    public function test_auto_suspension_for_due_invoice_is_pushed(): void
    {
        $this->travelTo(now()->startOfMonth()->addDays(9));

        Invoice::create([
            'customer_id' => $this->customer->id,
            'subscription_id' => $this->subscription->id,
            'number' => 'INV-1',
            'status' => 'overdue',
            'issue_date' => now()->subDays(9)->toDateString(),
            'due_date' => now()->subDays(8)->toDateString(),
            'subtotal' => 2000,
            'total' => 2000,
            'currency' => 'BDT',
        ]);

        $this->artisan('licenses:suspend-past-due --invoice-only')->assertSuccessful();
        app(MyBuildingLicenseSync::class)->flush();

        $this->assertSame('suspended', $this->license->fresh()->status);

        $last = collect($this->pushedStatuses())->last();
        $this->assertSame('suspended', $last['subscription_status']);
        $this->assertEquals(2000, $last['due_amount']);
    }

    public function test_paid_state_is_active_with_no_due(): void
    {
        $state = app(MyBuildingLicenseSync::class)->state($this->license);

        $this->assertSame('active', $state['subscription_status']);
        $this->assertEquals(0, $state['due_amount']);
        $this->assertSame($this->license->expires_at->toDateString(), $state['renews_at']);
    }

    public function test_deleting_subscription_tells_installation_it_is_cancelled(): void
    {
        $this->subscription->delete();
        app(MyBuildingLicenseSync::class)->flush();

        $last = collect($this->pushedStatuses())->last();
        $this->assertSame('cancelled', $last['subscription_status']);
        $this->assertSame('MBKEY0000000000000000000000000001', $last['license_key']);
    }

    public function test_building_change_from_installation_updates_provision_and_amount(): void
    {
        $body = json_encode([
            'license_key' => $this->license->license_key,
            'building_id' => 77,
            'building_name' => 'Tower Renamed',
            'total_floors' => 9,
            'contracted_flats' => 36,
            'total_flats' => 36,
            'active_flats' => 32,
            'inactive_floors' => ['9'],
            'action' => 'delete_floor',
        ]);
        $timestamp = (string) time();

        $response = $this->call('POST', '/api/licenses/sync', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_TIMESTAMP' => $timestamp,
            'HTTP_X_SIGNATURE' => hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET),
        ], $body);

        $response->assertOk()
            ->assertJson(['success' => true, 'subscription_status' => 'active', 'contracted_flats' => 36]);

        $provision = $this->provision->fresh();
        $this->assertSame('Tower Renamed', $provision->building_name);
        $this->assertSame(9, $provision->total_floors);
        $this->assertSame(36, $provision->contracted_flats);
        $this->assertSame(32, $provision->active_flats);
        $this->assertSame(['9'], $provision->inactive_floors);
        $this->assertEquals(1800.00, (float) $this->subscription->fresh()->subscription_amount);
        $this->assertSame(32, $this->license->fresh()->last_seats_reported);
    }

    public function test_building_sync_requires_valid_signature(): void
    {
        $body = json_encode(['license_key' => $this->license->license_key, 'contracted_flats' => 1]);

        $this->call('POST', '/api/licenses/sync', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_TIMESTAMP' => (string) time(),
            'HTTP_X_SIGNATURE' => 'forged',
        ], $body)->assertStatus(401);

        $this->assertSame(40, $this->provision->fresh()->contracted_flats);
    }
}
