<?php

namespace Tests\Feature;

use App\Jobs\SyncLicenseJob;
use App\Models\Customer;
use App\Models\License;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminLicenseSyncTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_queue_license_sync(): void
    {
        Queue::fake();

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

        Queue::assertPushed(SyncLicenseJob::class, function (SyncLicenseJob $job) use ($license) {
            return $job->licenseId === $license->id;
        });
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
