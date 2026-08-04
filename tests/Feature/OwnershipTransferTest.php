<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\License;
use App\Models\OwnershipTransfer;
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

class OwnershipTransferTest extends TestCase
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

    private function makeProjectWithSubscriptionAndLicense(Customer $customer): array
    {
        $product = Product::create(['name' => 'Transferable Product', 'slug' => 'transferable-product-'.uniqid(), 'status' => 'active']);
        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Standard Plan',
            'slug' => 'standard-plan-'.uniqid(),
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

        $project = Project::create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'name' => 'Transferable Project',
        ]);

        $license = License::create([
            'subscription_id' => $subscription->id,
            'product_id' => $product->id,
            'license_key' => License::generateKey(),
            'status' => 'active',
            'starts_at' => now()->toDateString(),
            'max_domains' => 1,
        ]);

        return [$project, $subscription, $license];
    }

    #[Test]
    public function service_moves_project_subscription_and_license_ownership_atomically_on_accept(): void
    {
        Mail::fake();

        [$fromCustomer] = $this->makeCustomerWithClient('Sender Co');
        [$toCustomer, $toUser] = $this->makeCustomerWithClient('Receiver Co');
        [$project, $subscription, $license] = $this->makeProjectWithSubscriptionAndLicense($fromCustomer);
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $service = app(ProjectTransferService::class);
        $transfer = $service->initiate($project, $toCustomer, $admin, 'handing off', null, '127.0.0.1');
        $plainToken = $transfer->plainToken;

        $this->assertDatabaseHas('ownership_transfers', [
            'id' => $transfer->id,
            'status' => 'pending',
            'from_customer_id' => $fromCustomer->id,
            'to_customer_id' => $toCustomer->id,
        ]);
        $this->assertDatabaseHas('ownership_transfer_logs', [
            'ownership_transfer_id' => $transfer->id,
            'action' => 'created',
        ]);

        $response = $this->actingAs($toUser)->post(route('client.transfers.accept', $transfer), [
            'token' => $plainToken,
        ]);

        $response->assertRedirect(route('client.transfers.index'));

        $this->assertSame($toCustomer->id, $project->fresh()->customer_id);
        $this->assertSame($toCustomer->id, $subscription->fresh()->customer_id);
        // License has no direct customer_id — ownership is derived through subscription_id,
        // so we confirm it's still attached to the (now-reassigned) subscription.
        $this->assertSame($subscription->id, $license->fresh()->subscription_id);

        $transfer->refresh();
        $this->assertSame('executed', $transfer->status);
        $this->assertNotNull($transfer->executed_at);
        $this->assertDatabaseHas('ownership_transfer_logs', [
            'ownership_transfer_id' => $transfer->id,
            'action' => 'accepted',
        ]);
        $this->assertDatabaseHas('ownership_transfer_logs', [
            'ownership_transfer_id' => $transfer->id,
            'action' => 'executed',
        ]);
        $this->assertDatabaseHas('status_audit_logs', [
            'model_type' => Project::class,
            'model_id' => $project->id,
            'new_status' => 'transferred',
        ]);
    }

    #[Test]
    public function reject_leaves_ownership_unchanged(): void
    {
        Mail::fake();

        [$fromCustomer] = $this->makeCustomerWithClient('Sender Co 2');
        [$toCustomer, $toUser] = $this->makeCustomerWithClient('Receiver Co 2');
        [$project, $subscription] = $this->makeProjectWithSubscriptionAndLicense($fromCustomer);
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $service = app(ProjectTransferService::class);
        $transfer = $service->initiate($project, $toCustomer, $admin, null, null, '127.0.0.1');
        $plainToken = $transfer->plainToken;

        $response = $this->actingAs($toUser)->post(route('client.transfers.reject', $transfer), [
            'token' => $plainToken,
        ]);

        $response->assertRedirect(route('client.transfers.index'));

        $this->assertSame($fromCustomer->id, $project->fresh()->customer_id);
        $this->assertSame($fromCustomer->id, $subscription->fresh()->customer_id);

        $transfer->refresh();
        $this->assertSame('rejected', $transfer->status);
        $this->assertNotNull($transfer->rejected_at);
    }

    #[Test]
    public function unrelated_customer_cannot_accept_even_with_a_guessed_transfer_id(): void
    {
        Mail::fake();

        [$fromCustomer] = $this->makeCustomerWithClient('Sender Co 3');
        [$toCustomer] = $this->makeCustomerWithClient('Receiver Co 3');
        [$outsiderCustomer, $outsiderUser] = $this->makeCustomerWithClient('Outsider Co');
        [$project] = $this->makeProjectWithSubscriptionAndLicense($fromCustomer);
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $service = app(ProjectTransferService::class);
        $transfer = $service->initiate($project, $toCustomer, $admin, null, null, '127.0.0.1');
        $plainToken = $transfer->plainToken;

        // Outsider has the *correct* token (e.g. leaked/guessed) but does not belong to the
        // receiving customer — the policy check must block this regardless of token validity.
        $response = $this->actingAs($outsiderUser)->post(route('client.transfers.accept', $transfer), [
            'token' => $plainToken,
        ]);

        $response->assertForbidden();
        $this->assertSame('pending', $transfer->fresh()->status);
    }

    #[Test]
    public function invalid_token_is_rejected_even_by_the_correct_customer(): void
    {
        Mail::fake();

        [$fromCustomer] = $this->makeCustomerWithClient('Sender Co 4');
        [$toCustomer, $toUser] = $this->makeCustomerWithClient('Receiver Co 4');
        [$project] = $this->makeProjectWithSubscriptionAndLicense($fromCustomer);
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $service = app(ProjectTransferService::class);
        $transfer = $service->initiate($project, $toCustomer, $admin, null, null, '127.0.0.1');

        $response = $this->actingAs($toUser)->post(route('client.transfers.accept', $transfer), [
            'token' => 'totally-wrong-token',
        ]);

        $response->assertForbidden();
        $this->assertSame('pending', $transfer->fresh()->status);
    }

    #[Test]
    public function expired_transfer_cannot_be_accepted(): void
    {
        Mail::fake();

        [$fromCustomer] = $this->makeCustomerWithClient('Sender Co 5');
        [$toCustomer, $toUser] = $this->makeCustomerWithClient('Receiver Co 5');
        [$project] = $this->makeProjectWithSubscriptionAndLicense($fromCustomer);
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $service = app(ProjectTransferService::class);
        $transfer = $service->initiate($project, $toCustomer, $admin, null, null, '127.0.0.1');
        $plainToken = $transfer->plainToken;

        $transfer->update(['token_expires_at' => now()->subDay()]);

        $response = $this->actingAs($toUser)->post(route('client.transfers.accept', $transfer), [
            'token' => $plainToken,
        ]);

        $response->assertStatus(422);
        $this->assertSame('pending', $transfer->fresh()->status);
    }

    #[Test]
    public function eligibility_error_blocks_a_project_whose_subscription_is_shared(): void
    {
        [$fromCustomer] = $this->makeCustomerWithClient('Sender Co 6');
        [$project, $subscription] = $this->makeProjectWithSubscriptionAndLicense($fromCustomer);

        // A second project riding on the same subscription makes it unsafe to move as a unit.
        Project::create([
            'customer_id' => $fromCustomer->id,
            'subscription_id' => $subscription->id,
            'name' => 'Sibling Project',
        ]);

        $service = app(ProjectTransferService::class);
        $error = $service->eligibilityError($project->fresh());

        $this->assertNotNull($error);
        $this->assertStringContainsString('shared by multiple projects', $error);
    }

    #[Test]
    public function scheduled_transfer_accepts_without_executing_then_command_executes_it_once_due(): void
    {
        Mail::fake();

        [$fromCustomer] = $this->makeCustomerWithClient('Sender Co 7');
        [$toCustomer, $toUser] = $this->makeCustomerWithClient('Receiver Co 7');
        [$project, $subscription] = $this->makeProjectWithSubscriptionAndLicense($fromCustomer);
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $service = app(ProjectTransferService::class);
        $scheduledFor = now()->addDay();
        $transfer = $service->initiate($project, $toCustomer, $admin, null, $scheduledFor, '127.0.0.1');
        $plainToken = $transfer->plainToken;

        $this->actingAs($toUser)->post(route('client.transfers.accept', $transfer), [
            'token' => $plainToken,
        ])->assertRedirect(route('client.transfers.index'));

        $transfer->refresh();
        $this->assertSame('accepted', $transfer->status);
        $this->assertNull($transfer->executed_at);
        $this->assertSame($fromCustomer->id, $project->fresh()->customer_id);

        // Not due yet — running the command now must not execute it.
        $this->artisan('ownership-transfers:run')->assertExitCode(0);
        $this->assertSame('accepted', $transfer->fresh()->status);
        $this->assertSame($fromCustomer->id, $project->fresh()->customer_id);

        // Fast-forward past the scheduled time and run again.
        \Carbon\Carbon::setTestNow($scheduledFor->copy()->addMinute());
        $this->artisan('ownership-transfers:run')->assertExitCode(0);

        $transfer->refresh();
        $this->assertSame('executed', $transfer->status);
        $this->assertSame($toCustomer->id, $project->fresh()->customer_id);
        $this->assertSame($toCustomer->id, $subscription->fresh()->customer_id);

        \Carbon\Carbon::setTestNow();
    }

    #[Test]
    public function stale_pending_invites_expire(): void
    {
        [$fromCustomer] = $this->makeCustomerWithClient('Sender Co 8');
        [$toCustomer] = $this->makeCustomerWithClient('Receiver Co 8');
        [$project] = $this->makeProjectWithSubscriptionAndLicense($fromCustomer);

        $transfer = OwnershipTransfer::create([
            'project_id' => $project->id,
            'subscription_id' => $project->subscription_id,
            'from_customer_id' => $fromCustomer->id,
            'to_customer_id' => $toCustomer->id,
            'status' => 'pending',
            'token_hash' => hash('sha256', 'whatever'),
            'token_expires_at' => now()->subDay(),
        ]);

        $expired = app(ProjectTransferService::class)->expireStale();

        $this->assertSame(1, $expired);
        $this->assertSame('expired', $transfer->fresh()->status);
    }

    #[Test]
    public function admin_store_endpoint_creates_a_pending_transfer_and_sends_invite(): void
    {
        Mail::fake();

        [$fromCustomer] = $this->makeCustomerWithClient('Sender Co 9');
        [$toCustomer] = $this->makeCustomerWithClient('Receiver Co 9');
        [$project] = $this->makeProjectWithSubscriptionAndLicense($fromCustomer);
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $response = $this->actingAs($admin)->post(route('admin.projects.transfers.store', $project), [
            'to_customer_id' => $toCustomer->id,
            'reason' => 'test transfer',
        ]);

        $response->assertRedirect(route('admin.projects.show', $project));

        $this->assertDatabaseHas('ownership_transfers', [
            'project_id' => $project->id,
            'from_customer_id' => $fromCustomer->id,
            'to_customer_id' => $toCustomer->id,
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function admin_store_endpoint_rejects_ineligible_project(): void
    {
        Mail::fake();

        [$fromCustomer] = $this->makeCustomerWithClient('Sender Co 10');
        [$toCustomer] = $this->makeCustomerWithClient('Receiver Co 10');
        [$project, $subscription] = $this->makeProjectWithSubscriptionAndLicense($fromCustomer);
        Project::create([
            'customer_id' => $fromCustomer->id,
            'subscription_id' => $subscription->id,
            'name' => 'Sibling Project 2',
        ]);
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $response = $this->actingAs($admin)->post(route('admin.projects.transfers.store', $project), [
            'to_customer_id' => $toCustomer->id,
        ]);

        $response->assertRedirect(route('admin.projects.show', $project));
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('ownership_transfers', ['project_id' => $project->id]);
    }
}
