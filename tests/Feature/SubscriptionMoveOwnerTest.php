<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Project;
use App\Models\ProjectMaintenance;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubscriptionMoveOwnerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function master_admin_can_transfer_subscription_owner_with_all_relations(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $currentCustomer = Customer::create(['name' => 'Original Customer']);
        $newCustomer = Customer::create(['name' => 'New Customer']);

        $product = \App\Models\Product::create([
            'name' => 'License Product',
            'slug' => 'license-product',
            'status' => 'active',
        ]);

        $plan = \App\Models\Plan::create([
            'product_id' => $product->id,
            'name' => 'Premium Plan',
            'slug' => 'premium-plan',
            'interval' => 'monthly',
            'price' => 100,
            'currency' => 'BDT',
            'is_active' => true,
        ]);

        $subscription = Subscription::create([
            'customer_id' => $currentCustomer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'current_period_start' => now()->toDateString(),
            'current_period_end' => now()->addMonth()->toDateString(),
            'next_invoice_at' => now()->toDateString(),
        ]);

        $project = Project::create([
            'customer_id' => $currentCustomer->id,
            'subscription_id' => $subscription->id,
            'name' => 'Project 1',
        ]);

        $maintenance = ProjectMaintenance::create([
            'project_id' => $project->id,
            'customer_id' => $currentCustomer->id,
            'title' => 'Monthly maintenance',
            'amount' => 50,
            'currency' => 'BDT',
            'billing_cycle' => 'monthly',
            'start_date' => now()->toDateString(),
            'next_billing_date' => now()->addMonth()->toDateString(),
        ]);

        $projectInvoice = Invoice::create([
            'customer_id' => $currentCustomer->id,
            'project_id' => $project->id,
            'number' => 'INV-PRJ-01',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'currency' => 'BDT',
            'subtotal' => 100,
            'total' => 100,
        ]);

        $subInvoice = Invoice::create([
            'customer_id' => $currentCustomer->id,
            'subscription_id' => $subscription->id,
            'number' => 'INV-SUB-01',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'currency' => 'BDT',
            'subtotal' => 50,
            'total' => 50,
        ]);

        $order = Order::create([
            'customer_id' => $currentCustomer->id,
            'subscription_id' => $subscription->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'order_number' => 'ORD-01',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.subscriptions.move-owner', $subscription), [
                'customer_id' => $newCustomer->id,
                'move_projects' => '1',
                'move_orders' => '1',
                'move_invoices' => '1',
            ]);

        $response->assertRedirect(route('admin.subscriptions.show', $subscription));
        $response->assertSessionHas('status', 'Subscription owner transferred successfully.');

        // Assert updates
        $this->assertEquals($newCustomer->id, $subscription->fresh()->customer_id);
        $this->assertEquals($newCustomer->id, $project->fresh()->customer_id);
        $this->assertEquals($newCustomer->id, $maintenance->fresh()->customer_id);
        $this->assertEquals($newCustomer->id, $projectInvoice->fresh()->customer_id);
        $this->assertEquals($newCustomer->id, $subInvoice->fresh()->customer_id);
        $this->assertEquals($newCustomer->id, $order->fresh()->customer_id);
    }

    #[Test]
    public function master_admin_can_transfer_subscription_only_without_relations(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $currentCustomer = Customer::create(['name' => 'Original Customer']);
        $newCustomer = Customer::create(['name' => 'New Customer']);

        $product = \App\Models\Product::create([
            'name' => 'License Product',
            'slug' => 'license-product',
            'status' => 'active',
        ]);

        $plan = \App\Models\Plan::create([
            'product_id' => $product->id,
            'name' => 'Premium Plan',
            'slug' => 'premium-plan',
            'interval' => 'monthly',
            'price' => 100,
            'currency' => 'BDT',
            'is_active' => true,
        ]);

        $subscription = Subscription::create([
            'customer_id' => $currentCustomer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'current_period_start' => now()->toDateString(),
            'current_period_end' => now()->addMonth()->toDateString(),
            'next_invoice_at' => now()->toDateString(),
        ]);

        $project = Project::create([
            'customer_id' => $currentCustomer->id,
            'subscription_id' => $subscription->id,
            'name' => 'Project 1',
        ]);

        $order = Order::create([
            'customer_id' => $currentCustomer->id,
            'subscription_id' => $subscription->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'order_number' => 'ORD-01',
            'status' => 'approved',
        ]);

        $subInvoice = Invoice::create([
            'customer_id' => $currentCustomer->id,
            'subscription_id' => $subscription->id,
            'number' => 'INV-SUB-01',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'currency' => 'BDT',
            'subtotal' => 50,
            'total' => 50,
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.subscriptions.move-owner', $subscription), [
                'customer_id' => $newCustomer->id,
                'move_projects' => '0',
                'move_orders' => '0',
                'move_invoices' => '0',
            ]);

        $response->assertRedirect(route('admin.subscriptions.show', $subscription));

        // Assert subscription changed, relations remained unchanged
        $this->assertEquals($newCustomer->id, $subscription->fresh()->customer_id);
        $this->assertEquals($currentCustomer->id, $project->fresh()->customer_id);
        $this->assertEquals($currentCustomer->id, $order->fresh()->customer_id);
        $this->assertEquals($currentCustomer->id, $subInvoice->fresh()->customer_id);
    }
}
