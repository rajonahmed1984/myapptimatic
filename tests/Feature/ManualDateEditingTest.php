<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectMaintenance;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManualDateEditingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function master_admin_can_manually_update_subscription_dates_and_access_override(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $customer = Customer::create([
            'name' => 'Date Edit Client',
            'email' => 'client@example.com',
            'access_override_until' => null,
        ]);

        $product = Product::create([
            'name' => 'Date Product',
            'slug' => 'date-product',
            'status' => 'active',
        ]);

        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Monthly Plan',
            'slug' => 'monthly-plan',
            'interval' => 'monthly',
            'price' => 100,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => '2026-06-01',
            'current_period_start' => '2026-06-01',
            'current_period_end' => '2026-06-30',
            'next_invoice_at' => '2026-06-01',
        ]);

        // Submit update request with dates formatted in d-m-Y (WHMCS-style)
        $response = $this->actingAs($admin)->put(route('admin.subscriptions.update', $subscription), [
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => '15-06-2026',
            'current_period_start' => '10-06-2026',
            'current_period_end' => '20-06-2026',
            'next_invoice_at' => '01-07-2026',
            'access_override_until' => '25-06-2026',
            'auto_renew' => true,
            'cancel_at_period_end' => false,
        ]);

        $response->assertRedirect();

        $subscription = $subscription->fresh();
        $this->assertEquals('2026-06-15', $subscription->start_date?->toDateString());
        $this->assertEquals('2026-06-10', $subscription->current_period_start?->toDateString());
        $this->assertEquals('2026-06-20', $subscription->current_period_end?->toDateString());
        // next_invoice_at should be exactly July 1st, 2026 (preserving manual admin edit even if day is 1st)
        $this->assertEquals('2026-07-01', $subscription->next_invoice_at?->toDateString());

        $customer = $customer->fresh();
        $this->assertNotNull($customer->access_override_until);
        $this->assertEquals('2026-06-25 23:59:59', $customer->access_override_until->toDateTimeString());
    }

    #[Test]
    public function master_admin_can_manually_update_project_maintenance_dates_and_access_override(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $customer = Customer::create([
            'name' => 'Maint Client',
            'email' => 'client@example.com',
            'access_override_until' => null,
        ]);

        $project = Project::create([
            'name' => 'Maint Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        $maintenance = ProjectMaintenance::create([
            'project_id' => $project->id,
            'customer_id' => $customer->id,
            'title' => 'Monthly Hosting',
            'amount' => 50,
            'currency' => 'USD',
            'billing_cycle' => 'monthly',
            'start_date' => '2026-06-01',
            'next_billing_date' => '2026-06-01',
            'status' => 'active',
        ]);

        // Submit update request with dates formatted in d-m-Y (WHMCS-style)
        $response = $this->actingAs($admin)->patch(route('admin.project-maintenances.update', $maintenance), [
            'title' => 'Monthly Hosting Updated',
            'amount' => 60,
            'billing_cycle' => 'yearly',
            'start_date' => '15-06-2026',
            'next_billing_date' => '20-06-2026',
            'access_override_until' => '30-06-2026',
            'auto_invoice' => true,
            'sales_rep_visible' => false,
            'status' => 'active',
        ]);

        $response->assertRedirect();

        $maintenance = $maintenance->fresh();
        $this->assertEquals('Monthly Hosting Updated', $maintenance->title);
        $this->assertEquals(60.00, $maintenance->amount);
        $this->assertEquals('yearly', $maintenance->billing_cycle);
        $this->assertEquals('2026-06-15', $maintenance->start_date?->toDateString());
        $this->assertEquals('2026-06-20', $maintenance->next_billing_date?->toDateString());

        $customer = $customer->fresh();
        $this->assertNotNull($customer->access_override_until);
        $this->assertEquals('2026-06-30 23:59:59', $customer->access_override_until->toDateTimeString());
    }
}
