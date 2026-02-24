<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\License;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientPortalUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function dashboard_routes_render_inertia_for_regular_and_project_clients(): void
    {
        $customer = Customer::create(['name' => 'Portal Customer']);
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($client)
            ->get(route('client.dashboard'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Client\\/Dashboard\\/Index', false);

        $project = Project::create([
            'name' => 'Project Dashboard',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        $projectClient = User::factory()->create([
            'role' => Role::CLIENT_PROJECT,
            'customer_id' => $customer->id,
            'project_id' => $project->id,
        ]);

        $this->actingAs($projectClient)
            ->get(route('client.dashboard'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Client\\/Dashboard\\/ProjectMinimal', false);
    }

    #[Test]
    public function tasks_chats_and_licenses_routes_render_inertia_pages(): void
    {
        $customer = Customer::create(['name' => 'Portal Customer']);
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $project = Project::create([
            'name' => 'Task Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Visible Task',
            'status' => 'pending',
            'customer_visible' => true,
        ]);

        $product = Product::create([
            'name' => 'License Product',
            'slug' => 'license-product',
            'description' => 'Product',
            'status' => 'active',
        ]);

        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Starter',
            'slug' => 'starter',
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

        License::create([
            'subscription_id' => $subscription->id,
            'product_id' => $product->id,
            'license_key' => strtoupper(bin2hex(random_bytes(16))),
            'status' => 'active',
            'starts_at' => now()->toDateString(),
            'expires_at' => now()->addYear()->toDateString(),
            'max_domains' => 1,
        ]);

        $this->actingAs($client)
            ->get(route('client.tasks.index'))
            ->assertOk()
            ->assertSee('Client\\/Tasks\\/Index', false);

        $this->actingAs($client)
            ->get(route('client.chats.index'))
            ->assertOk()
            ->assertSee('Client\\/Chats\\/Index', false);

        $this->actingAs($client)
            ->get(route('client.licenses.index'))
            ->assertOk()
            ->assertSee('Client\\/Licenses\\/Index', false);
    }

    #[Test]
    public function orders_and_projects_routes_render_inertia_and_keep_task_visibility_contract(): void
    {
        $customer = Customer::create(['name' => 'Portal Customer']);
        $client = User::factory()->create([
            'role' => Role::CLIENT,
            'customer_id' => $customer->id,
        ]);

        $product = Product::create([
            'name' => 'Order Product',
            'slug' => 'order-product',
            'description' => 'Product',
            'status' => 'active',
        ]);

        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Pro',
            'slug' => 'pro',
            'interval' => 'monthly',
            'price' => 29.99,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $this->actingAs($client)
            ->get(route('client.orders.index'))
            ->assertOk()
            ->assertSee('Client\\/Orders\\/Index', false);

        $this->actingAs($client)
            ->get(route('client.orders.review', ['plan_id' => $plan->id]))
            ->assertOk()
            ->assertSee('Client\\/Orders\\/Review', false);

        $project = Project::create([
            'name' => 'Visibility Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);

        $visibleTask = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Visible Task',
            'status' => 'pending',
            'customer_visible' => true,
            'created_by' => $client->id,
        ]);

        $hiddenTask = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Hidden Task',
            'status' => 'pending',
            'customer_visible' => false,
            'created_by' => $client->id,
        ]);

        $this->actingAs($client)
            ->get(route('client.projects.index'))
            ->assertOk()
            ->assertSee('Client\\/Projects\\/Index', false);

        $response = $this->actingAs($client)->get(route('client.projects.show', $project));

        $response->assertOk();
        $response->assertSee('Client\\/Projects\\/Show', false);
        $response->assertSee($visibleTask->title);
        $response->assertDontSee($hiddenTask->title);
    }
}
