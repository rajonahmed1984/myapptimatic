<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\License;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminLicenseSuspendOverrideTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_set_auto_suspend_override_until_date_on_license_update(): void
    {
        $license = $this->makeLicense();
        $admin = User::factory()->create([
            'role' => 'master_admin',
        ]);

        $overrideDate = now()->addDays(5)->toDateString();

        $response = $this->actingAs($admin)
            ->put(route('admin.licenses.update', $license), [
                'subscription_id' => $license->subscription_id,
                'product_id' => $license->product_id,
                'license_key' => $license->license_key,
                'status' => 'active',
                'starts_at' => $license->starts_at->toDateString(),
                'expires_at' => $license->expires_at?->toDateString(),
                'auto_suspend_override_until' => $overrideDate,
                'allowed_domains' => 'example.com',
                'notes' => 'Override enabled for urgent grace period.',
            ]);

        $response->assertRedirect(route('admin.licenses.edit', $license));

        $this->assertSame($overrideDate, $license->fresh()->auto_suspend_override_until?->toDateString());
    }

    #[Test]
    public function admin_can_suspend_license_using_suspend_action(): void
    {
        $license = $this->makeLicense([
            'status' => 'active',
        ]);
        $admin = User::factory()->create([
            'role' => 'master_admin',
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.licenses.suspend', $license));

        $response->assertRedirect(route('admin.licenses.edit', $license));

        $this->assertDatabaseHas('licenses', [
            'id' => $license->id,
            'status' => 'suspended',
        ]);
    }

    private function makeLicense(array $overrides = []): License
    {
        $customer = Customer::create([
            'name' => 'Suspend Override Customer',
            'email' => 'suspend-override@example.test',
            'status' => 'active',
        ]);

        $product = Product::create([
            'name' => 'Suspend Override Product',
            'slug' => 'suspend-override-product-'.Str::random(8),
            'status' => 'active',
        ]);

        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Monthly Plan',
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
