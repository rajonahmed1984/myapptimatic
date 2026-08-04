<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\License;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ProjectTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubscriptionOwnershipTransferTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomerWithClient(string $name): array
    {
        $customer = Customer::create(['name' => $name, 'status' => 'active']);
        $user = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        return [$customer, $user];
    }

    private function makeSubscriptionWithLicense(Customer $customer): array
    {
        $product = Product::create(['name' => 'Sub Transfer Product', 'slug' => 'sub-transfer-product-'.uniqid(), 'status' => 'active']);
        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Sub Transfer Plan',
            'slug' => 'sub-transfer-plan-'.uniqid(),
            'interval' => 'monthly',
            'price' => 100,
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
            'next_invoice_at' => now()->addMonth()->toDateString(),
        ]);
        $license = License::create([
            'subscription_id' => $subscription->id,
            'product_id' => $product->id,
            'license_key' => License::generateKey(),
            'status' => 'active',
            'starts_at' => now()->toDateString(),
        ]);

        return [$subscription, $license, $product];
    }

    #[Test]
    public function subscription_without_a_project_can_be_transferred_and_moves_its_license(): void
    {
        Mail::fake();

        [$fromCustomer] = $this->makeCustomerWithClient('Sub Sender Co');
        [$toCustomer, $toUser] = $this->makeCustomerWithClient('Sub Receiver Co');
        [$subscription, $license] = $this->makeSubscriptionWithLicense($fromCustomer);
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $service = app(ProjectTransferService::class);
        $this->assertNull($service->eligibilityErrorForSubscription($subscription));

        $transfer = $service->initiateForSubscription($subscription, $toCustomer, $admin, null, null, '127.0.0.1');
        $this->assertNull($transfer->project_id);

        $this->actingAs($toUser)->post(route('client.transfers.accept', $transfer), [
            'token' => $transfer->plainToken,
        ])->assertRedirect(route('client.transfers.index'));

        $this->assertSame($toCustomer->id, $subscription->fresh()->customer_id);
        $this->assertSame($subscription->id, $license->fresh()->subscription_id);
        $this->assertSame('executed', $transfer->fresh()->status);
    }

    #[Test]
    public function subscription_with_exactly_one_linked_project_moves_that_project_too(): void
    {
        Mail::fake();

        [$fromCustomer] = $this->makeCustomerWithClient('Sub Sender Co 2');
        [$toCustomer, $toUser] = $this->makeCustomerWithClient('Sub Receiver Co 2');
        [$subscription] = $this->makeSubscriptionWithLicense($fromCustomer);
        $project = Project::create([
            'customer_id' => $fromCustomer->id,
            'subscription_id' => $subscription->id,
            'name' => 'Linked Project',
        ]);
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $service = app(ProjectTransferService::class);
        $transfer = $service->initiateForSubscription($subscription, $toCustomer, $admin, null, null, '127.0.0.1');
        $this->assertSame($project->id, $transfer->project_id);

        $this->actingAs($toUser)->post(route('client.transfers.accept', $transfer), [
            'token' => $transfer->plainToken,
        ])->assertRedirect(route('client.transfers.index'));

        $this->assertSame($toCustomer->id, $subscription->fresh()->customer_id);
        $this->assertSame($toCustomer->id, $project->fresh()->customer_id);
    }

    #[Test]
    public function subscription_with_multiple_linked_projects_is_ineligible(): void
    {
        [$fromCustomer] = $this->makeCustomerWithClient('Sub Sender Co 3');
        [$subscription] = $this->makeSubscriptionWithLicense($fromCustomer);
        Project::create(['customer_id' => $fromCustomer->id, 'subscription_id' => $subscription->id, 'name' => 'Project A']);
        Project::create(['customer_id' => $fromCustomer->id, 'subscription_id' => $subscription->id, 'name' => 'Project B']);

        $error = app(ProjectTransferService::class)->eligibilityErrorForSubscription($subscription->fresh());

        $this->assertNotNull($error);
        $this->assertStringContainsString('multiple projects', $error);
    }

    #[Test]
    public function admin_can_request_a_subscription_transfer_via_http(): void
    {
        Mail::fake();

        [$fromCustomer] = $this->makeCustomerWithClient('Sub Sender Co 4');
        [$toCustomer] = $this->makeCustomerWithClient('Sub Receiver Co 4');
        [$subscription] = $this->makeSubscriptionWithLicense($fromCustomer);
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $response = $this->actingAs($admin)->post(route('admin.subscriptions.transfers.store', $subscription), [
            'to_customer_id' => $toCustomer->id,
        ]);

        $response->assertRedirect(route('admin.subscriptions.show', $subscription));
        $this->assertDatabaseHas('ownership_transfers', [
            'subscription_id' => $subscription->id,
            'project_id' => null,
            'to_customer_id' => $toCustomer->id,
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function client_from_a_different_customer_cannot_request_a_transfer_for_someone_elses_subscription(): void
    {
        [$fromCustomer] = $this->makeCustomerWithClient('Sub Sender Co 5');
        [$toCustomer] = $this->makeCustomerWithClient('Sub Receiver Co 5');
        [$outsiderCustomer, $outsiderUser] = $this->makeCustomerWithClient('Sub Outsider Co');
        [$subscription] = $this->makeSubscriptionWithLicense($fromCustomer);

        // Not routed through admin.panel middleware for clients, so hit the policy directly —
        // this mirrors what the (not-yet-built) client-facing entry point would enforce.
        $allowed = app(\App\Policies\OwnershipTransferPolicy::class)->initiateForSubscription($outsiderUser, $subscription);

        $this->assertFalse($allowed);
    }
}
