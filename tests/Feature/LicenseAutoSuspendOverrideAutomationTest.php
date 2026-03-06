<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Subscription;
use App\Services\StatusUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LicenseAutoSuspendOverrideAutomationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function automation_skips_auto_suspending_licenses_with_active_override_date(): void
    {
        Setting::setValue('enable_suspension', 1);
        Setting::setValue('suspend_days', 0);

        [$customer, $subscription] = $this->createSubscriptionSetup();

        $overrideLicense = License::create([
            'subscription_id' => $subscription->id,
            'product_id' => $subscription->plan->product_id,
            'license_key' => strtoupper(Str::random(24)),
            'status' => 'active',
            'starts_at' => now()->subMonth()->toDateString(),
            'expires_at' => now()->addYear()->toDateString(),
            'max_domains' => 1,
            'auto_suspend_override_until' => now()->addDays(4)->toDateString(),
        ]);

        $normalLicense = License::create([
            'subscription_id' => $subscription->id,
            'product_id' => $subscription->plan->product_id,
            'license_key' => strtoupper(Str::random(24)),
            'status' => 'active',
            'starts_at' => now()->subMonth()->toDateString(),
            'expires_at' => now()->addYear()->toDateString(),
            'max_domains' => 1,
        ]);

        Invoice::create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'number' => 'INV-AUTO-SUSPEND-OVERRIDE-1',
            'status' => 'unpaid',
            'issue_date' => now()->subDays(8)->toDateString(),
            'due_date' => now()->subDays(2)->toDateString(),
            'subtotal' => 120,
            'late_fee' => 0,
            'total' => 120,
            'currency' => 'USD',
        ]);

        $service = app(StatusUpdateService::class);
        $service->updateInvoiceOverdueStatus(now()->startOfDay());
        $service->updateSubscriptionSuspensionStatus(now()->startOfDay());

        $this->assertSame('active', (string) $overrideLicense->fresh()->status);
        $this->assertSame('suspended', (string) $normalLicense->fresh()->status);
        $this->assertSame('suspended', (string) $subscription->fresh()->status);
    }

    private function createSubscriptionSetup(): array
    {
        $customer = Customer::create([
            'name' => 'Automation Override Customer',
            'email' => 'automation-override@example.test',
            'status' => 'active',
        ]);

        $product = Product::create([
            'name' => 'Automation Override Product',
            'slug' => 'automation-override-product-'.Str::random(8),
            'status' => 'active',
        ]);

        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Automation Plan',
            'slug' => 'automation-plan-'.Str::lower(Str::random(8)),
            'interval' => 'monthly',
            'price' => 19,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subMonth()->toDateString(),
            'current_period_start' => now()->subMonth()->toDateString(),
            'current_period_end' => now()->addMonth()->toDateString(),
            'next_invoice_at' => now()->toDateString(),
            'auto_renew' => true,
            'cancel_at_period_end' => false,
        ]);

        return [$customer, $subscription];
    }
}

