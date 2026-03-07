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
    public function domain_must_be_prebound_before_verification(): void
    {
        Setting::setValue('auto_bind_domains', 1);

        [$customer, $subscription, $license] = $this->createLicenseSetup(
            [],
            [],
            [],
            null
        );

        $response = $this->postJson(route('api.licenses.verify'), [
            'license_key' => $license->license_key,
            'domain' => 'https://www.Example.com',
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'blocked',
            'blocked' => true,
            'reason' => 'domain_not_allowed',
        ]);

        $this->assertDatabaseMissing('license_domains', [
            'license_id' => $license->id,
            'domain' => 'example.com',
            'status' => 'active',
        ]);
    }

    #[Test]
    public function due_notice_includes_due_date_and_amount_details(): void
    {
        Setting::setValue('auto_bind_domains', 1);

        [$customer, $subscription, $license] = $this->createLicenseSetup();

        $dueDate = now()->addDays(7)->toDateString();
        $dueDateDisplay = now()->addDays(7)->format('F j, Y');

        Invoice::create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'number' => 'INV-DUE-DETAILS-1',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => $dueDate,
            'subtotal' => 1000,
            'late_fee' => 0,
            'total' => 1000,
            'currency' => 'BDT',
            'type' => 'project_initial_payment',
        ]);

        $response = $this->postJson(route('api.licenses.verify'), [
            'license_key' => $license->license_key,
            'domain' => 'example.com',
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'active',
            'blocked' => false,
            'notice' => 'invoice_due',
            'notice_severity' => 'amber',
            'invoice_due_date' => $dueDate,
            'invoice_amount' => 1000.0,
            'invoice_amount_display' => 'Tk 1000.00',
            'invoice_overdue_days' => 0,
        ]);

        $this->assertSame(
            "The invoice due date is {$dueDateDisplay} and the amount is Tk 1000.00.",
            (string) $response->json('notice_message')
        );
    }

    #[Test]
    public function overdue_invoice_blocks_license_verification(): void
    {
        Setting::setValue('auto_bind_domains', 1);

        [$customer, $subscription, $license] = $this->createLicenseSetup();

        Invoice::create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'number' => 'INV-OVERDUE-1',
            'status' => 'overdue',
            'issue_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDays(3)->toDateString(),
            'subtotal' => 120,
            'late_fee' => 0,
            'total' => 120,
            'currency' => 'USD',
            'type' => 'project_initial_payment',
        ]);

        $response = $this->postJson(route('api.licenses.verify'), [
            'license_key' => $license->license_key,
            'domain' => 'example.com',
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'blocked',
            'blocked' => true,
            'reason' => 'invoice_overdue',
        ]);
    }

    #[Test]
    public function overdue_invoice_from_other_subscription_does_not_block_license_verification(): void
    {
        Setting::setValue('auto_bind_domains', 1);

        [$customer, $subscription, $license] = $this->createLicenseSetup();

        $otherSubscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $subscription->plan_id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'current_period_start' => now()->toDateString(),
            'current_period_end' => now()->addMonth()->toDateString(),
            'next_invoice_at' => now()->addMonth()->toDateString(),
        ]);

        Invoice::create([
            'customer_id' => $customer->id,
            'subscription_id' => $otherSubscription->id,
            'number' => 'INV-OTHER-SUB-OVERDUE-1',
            'status' => 'overdue',
            'issue_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDays(3)->toDateString(),
            'subtotal' => 120,
            'late_fee' => 0,
            'total' => 120,
            'currency' => 'USD',
            'type' => 'project_initial_payment',
        ]);

        $response = $this->postJson(route('api.licenses.verify'), [
            'license_key' => $license->license_key,
            'domain' => 'example.com',
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'active',
            'blocked' => false,
        ]);
    }

    #[Test]
    public function overdue_notice_message_escalates_and_highlight_strengthens(): void
    {
        Setting::setValue('auto_bind_domains', 1);

        [$customer, $subscription, $license] = $this->createLicenseSetup();

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'number' => 'INV-OVERDUE-ESCALATION-1',
            'status' => 'overdue',
            'issue_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
            'subtotal' => 1000,
            'late_fee' => 0,
            'total' => 1000,
            'currency' => 'BDT',
            'type' => 'project_initial_payment',
        ]);

        $cases = [
            [
                'days' => 1,
                'severity' => 'rose',
                'suffix' => '2 days to go to avoid suspension.',
            ],
            [
                'days' => 2,
                'severity' => 'rose',
                'suffix' => '1 day to go to avoid suspension.',
            ],
            [
                'days' => 3,
                'severity' => 'critical',
                'suffix' => 'Today is the last day to clear payment and avoid suspension.',
            ],
        ];

        foreach ($cases as $case) {
            $invoice->update([
                'due_date' => now()->subDays($case['days'])->toDateString(),
            ]);

            $response = $this->postJson(route('api.licenses.verify'), [
                'license_key' => $license->license_key,
                'domain' => 'example.com',
            ]);

            $response->assertOk();
            $response->assertJson([
                'status' => 'blocked',
                'blocked' => true,
                'reason' => 'invoice_overdue',
                'notice_severity' => $case['severity'],
                'invoice_overdue_days' => $case['days'],
            ]);

            $dueDateDisplay = now()->subDays($case['days'])->format('F j, Y');
            $expectedMessage = "The invoice due date is {$dueDateDisplay} and the amount is Tk 1000.00. {$case['suffix']}";
            $this->assertSame($expectedMessage, (string) $response->json('notice_message'));
        }
    }

    #[Test]
    public function auto_suspend_override_keeps_license_verification_active_until_override_date(): void
    {
        Setting::setValue('auto_bind_domains', 1);

        [$customer, $subscription, $license] = $this->createLicenseSetup(
            [],
            ['status' => 'suspended'],
            ['auto_suspend_override_until' => now()->addDays(3)->toDateString()]
        );

        Invoice::create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'number' => 'INV-OVERRIDE-KEEP-ACTIVE-1',
            'status' => 'overdue',
            'issue_date' => now()->subDays(8)->toDateString(),
            'due_date' => now()->subDays(2)->toDateString(),
            'subtotal' => 750,
            'late_fee' => 0,
            'total' => 750,
            'currency' => 'BDT',
            'type' => 'project_initial_payment',
        ]);

        $response = $this->postJson(route('api.licenses.verify'), [
            'license_key' => $license->license_key,
            'domain' => 'example.com',
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'active',
            'blocked' => false,
            'notice' => 'invoice_overdue',
            'notice_severity' => 'rose',
            'auto_suspend_override_until' => now()->addDays(3)->toDateString(),
            'auto_suspend_override_active' => true,
            'invoice_overdue_days' => 2,
        ]);
    }

    #[Test]
    public function due_notice_hides_amount_when_invoice_total_is_empty(): void
    {
        Setting::setValue('auto_bind_domains', 1);

        [$customer, $subscription, $license] = $this->createLicenseSetup();

        $dueDate = now()->addDays(4)->toDateString();
        $dueDateDisplay = now()->addDays(4)->format('F j, Y');

        Invoice::create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'number' => 'INV-DUE-NO-AMOUNT-1',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => $dueDate,
            'subtotal' => 0,
            'late_fee' => 0,
            'total' => 0,
            'currency' => 'BDT',
            'type' => 'project_initial_payment',
        ]);

        $response = $this->postJson(route('api.licenses.verify'), [
            'license_key' => $license->license_key,
            'domain' => 'example.com',
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'active',
            'blocked' => false,
            'notice' => 'invoice_due',
            'notice_severity' => 'amber',
            'invoice_due_date' => $dueDate,
            'invoice_amount' => null,
            'invoice_amount_display' => null,
            'invoice_overdue_days' => 0,
        ]);

        $message = (string) $response->json('notice_message');
        $this->assertSame("The invoice due date is {$dueDateDisplay}.", $message);
        $this->assertStringNotContainsString('amount is', $message);
    }

    #[Test]
    public function license_url_must_match_domain_when_provided(): void
    {
        Setting::setValue('auto_bind_domains', 1);

        [$customer, $subscription, $license] = $this->createLicenseSetup();

        $response = $this->postJson(route('api.licenses.verify'), [
            'license_key' => $license->license_key,
            'domain' => 'example.com',
            'license_url' => 'https://other.com',
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'blocked',
            'blocked' => true,
            'reason' => 'invalid_domain',
        ]);
    }

    #[Test]
    public function signature_is_required_when_enabled(): void
    {
        $previousRequire = getenv('AI_REQUIRE_SIGNED_VERIFY');
        $previousSecret = getenv('AI_VERIFY_SECRET');
        $previousTolerance = getenv('API_SIGNATURE_TOLERANCE_SECONDS');

        $this->setEnv('AI_REQUIRE_SIGNED_VERIFY', 'true');
        $this->setEnv('AI_VERIFY_SECRET', 'test-secret');
        $this->setEnv('API_SIGNATURE_TOLERANCE_SECONDS', '600');

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
            $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'test-secret');

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
        array $licenseOverrides = [],
        ?string $activeDomain = 'example.com'
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

        if ($activeDomain !== null) {
            LicenseDomain::create([
                'license_id' => $license->id,
                'domain' => strtolower($activeDomain),
                'status' => 'active',
                'verified_at' => now(),
            ]);
        }

        return [$customer, $subscription, $license];
    }

    private function restoreEnv(string $key, $value): void
    {
        if ($value === false || $value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv($key.'='.$value);
        $_ENV[$key] = (string) $value;
        $_SERVER[$key] = (string) $value;
    }

    private function setEnv(string $key, string $value): void
    {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
