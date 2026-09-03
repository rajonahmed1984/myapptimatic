<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\LicenseDomain;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectMaintenance;
use App\Models\StatusAuditLog;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The WHMCS-style move actions: a license can change hands on its own, and a
 * project can change owner without going through a field edit.
 */
class OwnershipMoveTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_license_can_be_moved_to_another_clients_subscription(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        [$fromCustomer, $fromSubscription] = $this->createSubscription('Seller');
        [$toCustomer, $toSubscription] = $this->createSubscription('Buyer');

        $license = License::create([
            'subscription_id' => $fromSubscription->id,
            'product_id' => $fromSubscription->plan->product_id,
            'license_key' => strtoupper(Str::random(24)),
            'status' => 'active',
            'starts_at' => now()->subMonth()->toDateString(),
            'expires_at' => now()->addYear()->toDateString(),
            'max_domains' => 1,
        ]);

        $domain = LicenseDomain::create([
            'license_id' => $license->id,
            'domain' => 'seller.example',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.licenses.move', $license), [
                'subscription_id' => $toSubscription->id,
                'keep_domains' => '0',
            ])
            ->assertRedirect();

        $license->refresh();

        $this->assertSame($toSubscription->id, $license->subscription_id);
        $this->assertSame(
            $toSubscription->plan->product_id,
            $license->product_id,
            'The license should follow the destination subscription\'s product.'
        );
        $this->assertSame(
            'revoked',
            $domain->fresh()->status,
            'The previous install must stop being authorised when domains are not kept.'
        );

        $this->assertDatabaseHas('status_audit_logs', [
            'model_type' => License::class,
            'model_id' => $license->id,
            'reason' => 'ownership_move',
            'new_status' => 'customer:'.$toCustomer->id,
        ]);
    }

    #[Test]
    public function moving_a_license_keeps_domains_when_asked(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        [, $fromSubscription] = $this->createSubscription('Seller');
        [, $toSubscription] = $this->createSubscription('Buyer');

        $license = License::create([
            'subscription_id' => $fromSubscription->id,
            'product_id' => $fromSubscription->plan->product_id,
            'license_key' => strtoupper(Str::random(24)),
            'status' => 'active',
            'starts_at' => now()->subMonth()->toDateString(),
            'expires_at' => now()->addYear()->toDateString(),
            'max_domains' => 1,
        ]);

        $domain = LicenseDomain::create([
            'license_id' => $license->id,
            'domain' => 'stays.example',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.licenses.move', $license), [
                'subscription_id' => $toSubscription->id,
                'keep_domains' => '1',
            ])
            ->assertRedirect();

        $this->assertSame('active', $domain->fresh()->status);
    }

    #[Test]
    public function a_license_cannot_be_moved_onto_a_cancelled_subscription(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        [, $fromSubscription] = $this->createSubscription('Seller');
        [, $toSubscription] = $this->createSubscription('Buyer');
        $toSubscription->update(['status' => 'cancelled']);

        $license = License::create([
            'subscription_id' => $fromSubscription->id,
            'product_id' => $fromSubscription->plan->product_id,
            'license_key' => strtoupper(Str::random(24)),
            'status' => 'active',
            'starts_at' => now()->subMonth()->toDateString(),
            'expires_at' => now()->addYear()->toDateString(),
            'max_domains' => 1,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.licenses.move', $license), [
                'subscription_id' => $toSubscription->id,
            ]);

        $this->assertSame(
            $fromSubscription->id,
            $license->fresh()->subscription_id,
            'The license must stay put when the destination is cancelled.'
        );
    }

    #[Test]
    public function a_project_can_be_moved_to_another_client_with_its_records(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $fromCustomer = Customer::create(['name' => 'Project Seller', 'status' => 'active']);
        $toCustomer = Customer::create(['name' => 'Project Buyer', 'status' => 'active']);

        $project = Project::create([
            'customer_id' => $fromCustomer->id,
            'name' => 'Portal Rebuild',
            'type' => 'website',
            'status' => 'ongoing',
            'currency' => 'BDT',
            'total_budget' => 1000,
            'initial_payment_amount' => 0,
        ]);

        $invoice = Invoice::create([
            'customer_id' => $fromCustomer->id,
            'project_id' => $project->id,
            'number' => 'PRJ-MOVE-1',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 500,
            'late_fee' => 0,
            'total' => 500,
            'currency' => 'BDT',
        ]);

        $maintenance = ProjectMaintenance::create([
            'project_id' => $project->id,
            'customer_id' => $fromCustomer->id,
            'title' => 'Monthly upkeep',
            'amount' => 100,
            'currency' => 'BDT',
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'next_billing_date' => now()->addMonth()->toDateString(),
        ]);

        // A portal user belonging to the previous client must lose access.
        $foreignUser = User::factory()->create([
            'role' => Role::CLIENT_PROJECT,
            'customer_id' => $fromCustomer->id,
        ]);
        $project->projectClients()->attach($foreignUser->id);

        $this->actingAs($admin)
            ->put(route('admin.projects.move-owner', $project), [
                'customer_id' => $toCustomer->id,
                'move_invoices' => '1',
                'move_maintenances' => '1',
            ])
            ->assertRedirect(route('admin.projects.show', $project));

        $this->assertSame($toCustomer->id, $project->fresh()->customer_id);
        $this->assertSame($toCustomer->id, $invoice->fresh()->customer_id);
        $this->assertSame($toCustomer->id, $maintenance->fresh()->customer_id);
        $this->assertDatabaseMissing('project_user', [
            'project_id' => $project->id,
            'user_id' => $foreignUser->id,
        ]);

        $this->assertDatabaseHas('status_audit_logs', [
            'model_type' => Project::class,
            'model_id' => $project->id,
            'reason' => 'ownership_move',
        ]);
    }

    /**
     * @return array{0: Customer, 1: Subscription}
     */
    private function createSubscription(string $label): array
    {
        $customer = Customer::create([
            'name' => $label.' '.Str::random(4),
            'email' => Str::lower($label).'-'.Str::random(6).'@example.test',
            'status' => 'active',
        ]);

        $product = Product::create([
            'name' => $label.' Product',
            'slug' => Str::lower($label).'-product-'.Str::lower(Str::random(6)),
            'status' => 'active',
        ]);

        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => $label.' Plan',
            'slug' => Str::lower($label).'-plan-'.Str::lower(Str::random(6)),
            'interval' => 'monthly',
            'price' => 100,
            'currency' => 'BDT',
            'is_active' => true,
        ]);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subMonth()->toDateString(),
            'current_period_start' => now()->startOfMonth()->toDateString(),
            'current_period_end' => now()->endOfMonth()->toDateString(),
            'next_invoice_at' => now()->addMonth()->startOfMonth()->toDateString(),
            'auto_renew' => true,
            'cancel_at_period_end' => false,
        ]);

        return [$customer, $subscription];
    }
}
