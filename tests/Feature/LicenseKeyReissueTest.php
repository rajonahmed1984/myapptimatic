<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\License;
use App\Models\LicenseDomain;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LicenseKeyReissueTest extends TestCase
{
    use RefreshDatabase;

    private function createLicenseSetup(): array
    {
        $product = Product::create(['name' => 'Reissue Test Product', 'slug' => 'reissue-test-product', 'status' => 'active']);
        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Reissue Test Plan',
            'slug' => 'reissue-test-plan',
            'interval' => 'monthly',
            'price' => 0,
            'currency' => 'USD',
            'is_active' => true,
        ]);
        $customer = Customer::create(['name' => 'Reissue Customer', 'status' => 'active']);
        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'current_period_start' => now()->toDateString(),
            'current_period_end' => now()->addMonth()->toDateString(),
            'next_invoice_at' => now()->addMonth()->toDateString(),
        ]);
        $license = License::create([
            'subscription_id' => $subscription->id,
            'product_id' => $product->id,
            'license_key' => 'OLD-KEY-'.uniqid(),
            'status' => 'active',
            'starts_at' => now()->toDateString(),
        ]);
        LicenseDomain::create([
            'license_id' => $license->id,
            'domain' => 'example.com',
            'status' => 'active',
            'verified_at' => now(),
        ]);

        return [$license, $customer];
    }

    #[Test]
    public function reissuing_generates_a_new_key_and_the_old_key_stops_verifying(): void
    {
        Setting::setValue('auto_bind_domains', 1);

        [$license] = $this->createLicenseSetup();
        $oldKey = $license->license_key;
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $response = $this->actingAs($admin)->post(route('admin.licenses.reissue-key', $license), [
            'reason' => 'customer requested rotation',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $license->refresh();
        $this->assertNotSame($oldKey, $license->license_key);

        // Old key must no longer verify.
        $this->postJson(route('api.licenses.verify'), [
            'license_key' => $oldKey,
            'domain' => 'example.com',
        ])->assertJsonFragment(['blocked' => true, 'reason' => 'license_not_found']);

        // New key verifies successfully.
        $this->postJson(route('api.licenses.verify'), [
            'license_key' => $license->license_key,
            'domain' => 'example.com',
        ])->assertJsonFragment(['blocked' => false]);
    }

    #[Test]
    public function reissue_is_recorded_in_the_audit_log_with_masked_keys(): void
    {
        [$license] = $this->createLicenseSetup();
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $this->actingAs($admin)->post(route('admin.licenses.reissue-key', $license), []);

        $log = \App\Models\StatusAuditLog::query()
            ->where('model_type', License::class)
            ->where('model_id', $license->id)
            ->where('reason', 'key_reissued')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringStartsWith('****', $log->old_status);
        $this->assertStringStartsWith('****', $log->new_status);
        $this->assertStringNotContainsString($license->fresh()->license_key, $log->old_status);
    }

    #[Test]
    public function non_admin_role_cannot_reissue_a_license_key(): void
    {
        [$license, $customer] = $this->createLicenseSetup();
        $support = User::factory()->create(['role' => Role::SUPPORT]);

        $response = $this->actingAs($support)->post(route('admin.licenses.reissue-key', $license), []);

        $response->assertForbidden();
    }
}
