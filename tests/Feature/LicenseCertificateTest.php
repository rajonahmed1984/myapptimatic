<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\License;
use App\Models\LicenseDomain;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\LicenseCertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LicenseCertificateTest extends TestCase
{
    use RefreshDatabase;

    private function createLicenseSetup(): License
    {
        $product = Product::create(['name' => 'Cert Test Product', 'slug' => 'cert-test-product', 'status' => 'active']);
        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Cert Test Plan',
            'slug' => 'cert-test-plan',
            'interval' => 'monthly',
            'price' => 0,
            'currency' => 'USD',
            'is_active' => true,
            'seat_limit' => 5,
        ]);
        $customer = Customer::create(['name' => 'Cert Customer', 'status' => 'active']);
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
            'license_key' => 'CERT-TEST-'.uniqid(),
            'status' => 'active',
            'starts_at' => now()->toDateString(),
            'expires_at' => now()->addYear()->toDateString(),
        ]);
        LicenseDomain::create([
            'license_id' => $license->id,
            'domain' => 'cert-example.com',
            'status' => 'active',
            'verified_at' => now(),
        ]);

        return $license;
    }

    #[Test]
    public function issued_certificate_signature_verifies_against_the_configured_public_key(): void
    {
        $license = $this->createLicenseSetup();
        $service = app(LicenseCertificateService::class);

        $cert = $service->issue($license, null);

        $this->assertSame('active', $cert->status);
        $this->assertSame($license->id, $cert->license_id);
        $this->assertSame($license->license_key, $cert->payload['license_key']);
        $this->assertSame('cert-example.com', $cert->payload['domain']);
        $this->assertSame(5, $cert->payload['seat_limit']);
        $this->assertTrue($service->verify($cert));
    }

    #[Test]
    public function tampering_with_the_payload_invalidates_the_signature(): void
    {
        $license = $this->createLicenseSetup();
        $service = app(LicenseCertificateService::class);

        $cert = $service->issue($license, null);
        $cert->payload = array_merge($cert->payload, ['seat_limit' => 999]);

        $this->assertFalse($service->verify($cert));
    }

    #[Test]
    public function reissuing_revokes_the_previous_certificate(): void
    {
        $license = $this->createLicenseSetup();
        $service = app(LicenseCertificateService::class);

        $first = $service->issue($license, null);
        $second = $service->issue($license, null);

        $this->assertSame('revoked', $first->fresh()->status);
        $this->assertSame('active', $second->fresh()->status);
        $this->assertNotSame($first->cert_uuid, $second->cert_uuid);
    }

    #[Test]
    public function admin_can_issue_and_revoke_a_certificate_via_http(): void
    {
        $license = $this->createLicenseSetup();
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $this->actingAs($admin)
            ->post(route('admin.licenses.certificate.issue', $license))
            ->assertRedirect();

        $this->assertDatabaseHas('license_certificates', [
            'license_id' => $license->id,
            'status' => 'active',
        ]);

        $cert = $license->certificates()->where('status', 'active')->first();

        $this->actingAs($admin)
            ->post(route('admin.licenses.certificate.revoke', [$license, $cert]))
            ->assertRedirect();

        $this->assertSame('revoked', $cert->fresh()->status);
    }

    #[Test]
    public function download_endpoint_returns_the_active_certificate_payload_and_signature(): void
    {
        $license = $this->createLicenseSetup();
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $cert = app(LicenseCertificateService::class)->issue($license, $admin->id);

        $response = $this->actingAs($admin)->get(route('admin.licenses.certificate.download', $license));

        $response->assertOk();
        $response->assertJsonFragment(['key_id' => $cert->key_id]);
        $response->assertJsonPath('payload.cert_uuid', $cert->cert_uuid);
    }
}
