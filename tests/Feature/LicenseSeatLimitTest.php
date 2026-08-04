<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\License;
use App\Models\LicenseDomain;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LicenseSeatLimitTest extends TestCase
{
    use RefreshDatabase;

    private function createLicenseSetup(array $planOverrides = [], array $licenseOverrides = []): array
    {
        $product = Product::create(['name' => 'Seat Test Product', 'slug' => 'seat-test-product', 'status' => 'active']);

        $plan = Plan::create(array_merge([
            'product_id' => $product->id,
            'name' => 'Seat Test Plan',
            'slug' => 'seat-test-plan',
            'interval' => 'monthly',
            'price' => 0,
            'currency' => 'USD',
            'is_active' => true,
        ], $planOverrides));

        $customer = Customer::create(['name' => 'Seat Limit Customer', 'status' => 'active']);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'current_period_start' => now()->toDateString(),
            'current_period_end' => now()->addMonth()->toDateString(),
            'next_invoice_at' => now()->addMonth()->toDateString(),
        ]);

        $license = License::create(array_merge([
            'subscription_id' => $subscription->id,
            'product_id' => $product->id,
            'license_key' => 'SEAT-TEST-'.uniqid(),
            'status' => 'active',
            'starts_at' => now()->toDateString(),
            'expires_at' => now()->addDays(30)->toDateString(),
        ], $licenseOverrides));

        LicenseDomain::create([
            'license_id' => $license->id,
            'domain' => 'example.com',
            'status' => 'active',
            'verified_at' => now(),
        ]);

        return [$customer, $subscription, $license];
    }

    #[Test]
    public function verification_succeeds_when_seats_in_use_is_within_the_license_override_limit(): void
    {
        Setting::setValue('auto_bind_domains', 1);

        [, , $license] = $this->createLicenseSetup([], ['seat_limit' => 5]);

        $response = $this->postJson(route('api.licenses.verify'), [
            'license_key' => $license->license_key,
            'domain' => 'example.com',
            'seats_in_use' => 5,
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['blocked' => false, 'seat_limit' => 5, 'seats_in_use' => 5]);
        $this->assertSame(5, $license->fresh()->last_seats_reported);
    }

    #[Test]
    public function verification_is_blocked_when_seats_in_use_exceeds_the_license_override_limit(): void
    {
        Setting::setValue('auto_bind_domains', 1);

        [, , $license] = $this->createLicenseSetup([], ['seat_limit' => 3]);

        $response = $this->postJson(route('api.licenses.verify'), [
            'license_key' => $license->license_key,
            'domain' => 'example.com',
            'seats_in_use' => 4,
        ]);

        $response->assertOk();
        $response->assertJsonFragment([
            'status' => 'blocked',
            'blocked' => true,
            'reason' => 'seat_limit_exceeded',
            'seat_limit' => 3,
            'seats_in_use' => 4,
        ]);
    }

    #[Test]
    public function license_seat_limit_override_takes_precedence_over_the_plan_default(): void
    {
        Setting::setValue('auto_bind_domains', 1);

        [, , $license] = $this->createLicenseSetup(['seat_limit' => 2], ['seat_limit' => 10]);

        $response = $this->postJson(route('api.licenses.verify'), [
            'license_key' => $license->license_key,
            'domain' => 'example.com',
            'seats_in_use' => 8,
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['blocked' => false, 'seat_limit' => 10]);
    }

    #[Test]
    public function plan_default_seat_limit_is_used_when_license_has_no_override(): void
    {
        Setting::setValue('auto_bind_domains', 1);

        [, , $license] = $this->createLicenseSetup(['seat_limit' => 2], []);

        $response = $this->postJson(route('api.licenses.verify'), [
            'license_key' => $license->license_key,
            'domain' => 'example.com',
            'seats_in_use' => 3,
        ]);

        $response->assertOk();
        $response->assertJsonFragment([
            'blocked' => true,
            'reason' => 'seat_limit_exceeded',
            'seat_limit' => 2,
        ]);
    }

    #[Test]
    public function null_seat_limit_never_blocks_regardless_of_seats_in_use(): void
    {
        Setting::setValue('auto_bind_domains', 1);

        [, , $license] = $this->createLicenseSetup([], []);

        $response = $this->postJson(route('api.licenses.verify'), [
            'license_key' => $license->license_key,
            'domain' => 'example.com',
            'seats_in_use' => 999999,
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['blocked' => false]);
    }

    #[Test]
    public function omitting_seats_in_use_does_not_affect_existing_verification_behavior(): void
    {
        Setting::setValue('auto_bind_domains', 1);

        [, , $license] = $this->createLicenseSetup([], ['seat_limit' => 1]);

        $response = $this->postJson(route('api.licenses.verify'), [
            'license_key' => $license->license_key,
            'domain' => 'example.com',
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['blocked' => false]);
        $this->assertNull($license->fresh()->last_seats_reported);
    }

    #[Test]
    public function repeated_verification_from_a_new_ip_is_flagged_as_an_anomaly_but_not_blocked(): void
    {
        Setting::setValue('auto_bind_domains', 1);

        [, , $license] = $this->createLicenseSetup();

        $this->postJson(route('api.licenses.verify'), [
            'license_key' => $license->license_key,
            'domain' => 'example.com',
        ], ['REMOTE_ADDR' => '10.0.0.1'])->assertOk();

        $this->assertSame('10.0.0.1', $license->fresh()->last_check_ip);

        $response = $this->postJson(route('api.licenses.verify'), [
            'license_key' => $license->license_key,
            'domain' => 'example.com',
        ], ['REMOTE_ADDR' => '10.0.0.2']);

        $response->assertOk();
        $response->assertJsonFragment(['blocked' => false]);
        $this->assertSame('10.0.0.2', $license->fresh()->last_check_ip);

        $log = \Illuminate\Support\Facades\DB::table('license_usage_logs')
            ->where('license_id', $license->id)
            ->latest('id')
            ->first();
        $metadata = json_decode((string) $log->metadata, true);

        $this->assertTrue($metadata['ip_anomaly']);
        $this->assertSame('10.0.0.1', $metadata['previous_ip']);
    }
}
