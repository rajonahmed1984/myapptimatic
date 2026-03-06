<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\LicenseDomain;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminLicenseSyncTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_run_license_sync_realtime(): void
    {
        $license = $this->makeLicense();
        $admin = User::factory()->create([
            'role' => 'master_admin',
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.licenses.sync', $license));

        $response->assertOk()
            ->assertJson([
                'ok' => true,
            ]);

        $this->assertNotNull($license->fresh()->last_check_at);
    }

    #[Test]
    public function admin_can_fetch_license_sync_status(): void
    {
        $license = $this->makeLicense([
            'last_check_at' => now()->subHour(),
            'last_check_ip' => '127.0.0.1',
        ]);
        $admin = User::factory()->create([
            'role' => 'master_admin',
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.licenses.sync-status', $license));

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'data' => [
                    'sync_label' => 'Synced',
                ],
            ]);
    }

    #[Test]
    public function admin_sync_respects_auto_suspend_override_for_suspended_license(): void
    {
        Setting::setValue('grace_period_days', 0);

        $license = $this->makeLicense([
            'status' => 'suspended',
            'auto_suspend_override_until' => now()->addDays(2)->toDateString(),
        ]);

        $license->subscription()->update(['status' => 'suspended']);

        LicenseDomain::create([
            'license_id' => $license->id,
            'domain' => 'example.com',
            'status' => 'active',
            'verified_at' => now(),
        ]);

        Invoice::create([
            'customer_id' => $license->subscription->customer_id,
            'number' => 'INV-ADMIN-SYNC-OVERRIDE-1',
            'status' => 'overdue',
            'issue_date' => now()->subDays(8)->toDateString(),
            'due_date' => now()->subDays(3)->toDateString(),
            'subtotal' => 120,
            'late_fee' => 0,
            'total' => 120,
            'currency' => 'USD',
            'type' => 'project_initial_payment',
        ]);

        $admin = User::factory()->create([
            'role' => 'master_admin',
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.licenses.sync', $license));

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'data' => [
                    'is_verified' => true,
                    'reason' => null,
                ],
            ]);

        $this->assertNotNull($license->fresh()->last_verified_at);
    }

    private function makeLicense(array $overrides = []): License
    {
        $customer = Customer::create([
            'name' => 'License Customer',
            'email' => 'customer@example.test',
            'status' => 'active',
        ]);

        $product = Product::create([
            'name' => 'License Product',
            'slug' => 'license-product-' . Str::random(8),
            'status' => 'active',
        ]);

        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Starter Plan',
            'interval' => 'monthly',
            'price' => 19.00,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $startDate = now()->startOfDay();
        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => $startDate->toDateString(),
            'current_period_start' => $startDate->toDateString(),
            'current_period_end' => $startDate->copy()->addMonth()->subDay()->toDateString(),
            'next_invoice_at' => $startDate->copy()->addMonth()->toDateString(),
            'auto_renew' => true,
            'cancel_at_period_end' => false,
        ]);

        $payload = array_merge([
            'subscription_id' => $subscription->id,
            'product_id' => $product->id,
            'license_key' => strtoupper(Str::random(24)),
            'status' => 'active',
            'starts_at' => $startDate->toDateString(),
            'expires_at' => $startDate->copy()->addYear()->toDateString(),
            'max_domains' => 1,
        ], $overrides);

        return License::create($payload);
    }
}
