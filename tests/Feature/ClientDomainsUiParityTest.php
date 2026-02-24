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
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientDomainsUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function client_domains_index_renders_inertia_page(): void
    {
        $customer = Customer::create(['name' => 'Domain Customer']);
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($client)
            ->get(route('client.domains.index'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Client\\/Domains\\/Index', false);
    }

    #[Test]
    public function client_domains_show_renders_inertia_for_owner(): void
    {
        $customer = Customer::create(['name' => 'Domain Customer']);
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $domain = $this->createDomainForCustomer($customer, 'owner.example.com');

        $this->actingAs($client)
            ->get(route('client.domains.show', $domain))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Client\\/Domains\\/Show', false);
    }

    #[Test]
    public function client_domains_show_returns_not_found_for_other_customer(): void
    {
        $owner = Customer::create(['name' => 'Owner']);
        $other = Customer::create(['name' => 'Other']);

        $otherClient = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $other->id,
        ]);

        $domain = $this->createDomainForCustomer($owner, 'blocked.example.com');

        $this->actingAs($otherClient)
            ->get(route('client.domains.show', $domain))
            ->assertNotFound();
    }

    private function createDomainForCustomer(Customer $customer, string $domainName): LicenseDomain
    {
        $product = Product::create([
            'name' => 'Domain Product',
            'slug' => 'domain-product',
            'description' => 'Domain service',
            'status' => 'active',
        ]);

        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Domain Plan',
            'slug' => 'domain-plan',
            'interval' => 'monthly',
            'price' => 9.99,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'current_period_start' => now()->toDateString(),
            'current_period_end' => now()->addMonth()->toDateString(),
            'next_invoice_at' => now()->addMonth(),
            'auto_renew' => true,
        ]);

        $license = License::create([
            'subscription_id' => $subscription->id,
            'product_id' => $product->id,
            'license_key' => strtoupper(bin2hex(random_bytes(16))),
            'status' => 'active',
            'starts_at' => now()->toDateString(),
            'expires_at' => now()->addYear()->toDateString(),
            'max_domains' => 1,
        ]);

        return LicenseDomain::create([
            'license_id' => $license->id,
            'domain' => $domainName,
            'status' => 'active',
            'verified_at' => now(),
            'last_seen_at' => now(),
        ]);
    }
}
