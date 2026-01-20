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

class LicenseVerificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function expired_license_is_blocked(): void
    {
        Setting::setValue('auto_bind_domains', 1);

        [$customer, $subscription, $license] = $this->createLicenseSetup(
            [],
            [],
            ['expires_at' => now()->subDay()->toDateString()]
        );

        $response = $this->postJson(route('api.licenses.verify'), [
            'license_key' => $license->license_key,
            'domain' => 'example.com',
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'blocked',
            'blocked' => true,
            'reason' => 'license_expired',
        ]);
    }

    #[Test]
    public function inactive_license_is_blocked(): void
    {
        Setting::setValue('auto_bind_domains', 1);

        [$customer, $subscription, $license] = $this->createLicenseSetup(
            [],
            [],
            ['status' => 'suspended']
        );

        $response = $this->postJson(route('api.licenses.verify'), [
            'license_key' => $license->license_key,
            'domain' => 'example.com',
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'blocked',
            'blocked' => true,
            'reason' => 'license_inactive',
        ]);
    }

    #[Test]
    public function subscription_inactive_is_blocked(): void
    {
        Setting::setValue('auto_bind_domains', 1);

        [$customer, $subscription, $license] = $this->createLicenseSetup(
            [],
            ['status' => 'suspended'],
            []
        );

        $response = $this->postJson(route('api.licenses.verify'), [
            'license_key' => $license->license_key,
            'domain' => 'example.com',
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'blocked',
            'blocked' => true,
            'reason' => 'subscription_inactive',
        ]);
    }

    #[Test]
    public function domain_mismatch_is_blocked_when_active_domain_exists(): void
    {
        Setting::setValue('auto_bind_domains', 1);

        [$customer, $subscription, $license] = $this->createLicenseSetup();

        LicenseDomain::create([
            'license_id' => $license->id,
            'domain' => 'example.com',
            'status' => 'active',
        ]);

        $response = $this->postJson(route('api.licenses.verify'), [
            'license_key' => $license->license_key,
            'domain' => 'other.com',
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'blocked',
            'blocked' => true,
            'reason' => 'domain_not_allowed',
        ]);
    }

    #[Test]
    public function auto_bind_adds_domain_when_allowed(): void
    {
        Setting::setValue('auto_bind_domains', 1);

        [$customer, $subscription, $license] = $this->createLicenseSetup();

        $response = $this->postJson(route('api.licenses.verify'), [
            'license_key' => $license->license_key,
            'domain' => 'https://www.Example.com',
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'active',
            'blocked' => false,
            'domain' => 'example.com',
        ]);

        $this->assertDatabaseHas('license_domains', [
            'license_id' => $license->id,
            'domain' => 'example.com',
            'status' => 'active',
        ]);
    }

    #[Test]
    public function signature_is_required_when_enabled(): void
    {
        $previousRequire = getenv('AI_REQUIRE_SIGNED_VERIFY');
        $previousSecret = getenv('AI_VERIFY_SECRET');
        $previousTolerance = getenv('API_SIGNATURE_TOLERANCE_SECONDS');

        putenv('AI_REQUIRE_SIGNED_VERIFY=true');
        putenv('AI_VERIFY_SECRET=test-secret');
        putenv('API_SIGNATURE_TOLERANCE_SECONDS=600');

        try {
            Setting::setValue('auto_bind_domains', 1);
            [$customer, $subscription, $license] = $this->createLicenseSetup();

            $payload = [
                'license_key' => $license->license_key,
                'domain' => 'example.com',
            ];

            $this->postJson(route('api.licenses.verify'), $payload)
                ->assertStatus(401);

            $timestamp = (string) time();
            $body = json_encode($payload);
            $signature = hash_hmac('sha256', $timestamp . '.' . $body, 'test-secret');

            $this->withHeaders([
                'X-Timestamp' => $timestamp,
                'X-Signature' => $signature,
            ])->postJson(route('api.licenses.verify'), $payload)
                ->assertOk()
                ->assertJson([
                    'status' => 'active',
                    'blocked' => false,
                ]);
        } finally {
            $this->restoreEnv('AI_REQUIRE_SIGNED_VERIFY', $previousRequire);
            $this->restoreEnv('AI_VERIFY_SECRET', $previousSecret);
            $this->restoreEnv('API_SIGNATURE_TOLERANCE_SECONDS', $previousTolerance);
        }
    }

    private function createLicenseSetup(
        array $customerOverrides = [],
        array $subscriptionOverrides = [],
        array $licenseOverrides = []
    ): array {
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'status' => 'active',
        ]);

        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Test Plan',
            'slug' => 'test-plan',
            'interval' => 'monthly',
            'price' => 0,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $customer = Customer::create(array_merge([
            'name' => 'License Customer',
            'status' => 'active',
        ], $customerOverrides));

        $subscription = Subscription::create(array_merge([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'current_period_start' => now()->toDateString(),
            'current_period_end' => now()->addMonth()->toDateString(),
            'next_invoice_at' => now()->addMonth()->toDateString(),
        ], $subscriptionOverrides));

        $license = License::create(array_merge([
            'subscription_id' => $subscription->id,
            'product_id' => $product->id,
            'license_key' => 'TEST-LICENSE-KEY',
            'status' => 'active',
            'starts_at' => now()->toDateString(),
            'expires_at' => now()->addDays(30)->toDateString(),
        ], $licenseOverrides));

        return [$customer, $subscription, $license];
    }

    private function restoreEnv(string $key, $value): void
    {
        if ($value === false || $value === null) {
            putenv($key);
            return;
        }

        putenv($key . '=' . $value);
    }
}
