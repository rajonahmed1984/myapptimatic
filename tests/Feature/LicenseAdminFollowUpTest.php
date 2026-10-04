<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AccessBlockService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LicenseAdminFollowUpTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function non_admin_panel_roles_cannot_manage_subscriptions_or_list_licenses(): void
    {
        config(['admin.panel_roles' => ['master_admin', 'sub_admin', 'admin', 'sales', 'support']]);
        $sales = User::factory()->create(['role' => 'sales']);
        $subscription = $this->subscription($this->customer());

        $this->actingAs($sales)->get(route('admin.subscriptions.index'))->assertForbidden();
        $this->actingAs($sales)->delete(route('admin.subscriptions.destroy', $subscription))->assertForbidden();
        $this->actingAs($sales)->get(route('admin.licenses.index'))->assertForbidden();

        $this->assertNotNull($subscription->fresh());
    }

    #[Test]
    public function the_nightly_check_does_not_mark_a_silent_installation_as_synced(): void
    {
        $license = $this->license($this->subscription($this->customer()), [
            'last_check_at' => now()->subDays(10),
            'last_check_ip' => '198.51.100.4',
        ]);

        (new \App\Jobs\SyncLicenseJob($license->id, null))->handle(app(\App\Services\LicenseRealtimeCheckService::class));

        $license->refresh();
        $this->assertSame('198.51.100.4', $license->last_check_ip);
        $this->assertTrue($license->last_check_at->lessThan(now()->subDays(9)));
        $this->assertNotNull($license->last_server_check_at);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->getJson(route('admin.licenses.sync-status', $license))
            ->assertJsonPath('data.sync_label', 'Stale');
    }

    #[Test]
    public function a_client_check_within_48_hours_reads_as_synced(): void
    {
        $license = $this->license($this->subscription($this->customer()), ['last_check_at' => now()->subHours(36)]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->getJson(route('admin.licenses.sync-status', $license))
            ->assertJsonPath('data.sync_label', 'Synced');
    }

    #[Test]
    public function an_active_license_past_its_expiry_is_suspended_the_next_day(): void
    {
        Carbon::setTestNow('2026-10-20 09:00:00');
        $license = $this->license($this->subscription($this->customer()), ['expires_at' => '2026-10-19']);

        $this->artisan('licenses:suspend-past-due', ['--expiry-only' => true])->assertSuccessful();

        $this->assertSame('suspended', $license->fresh()->status);
    }

    #[Test]
    public function the_batched_block_map_matches_the_per_subscription_check(): void
    {
        Carbon::setTestNow('2026-10-20 09:00:00');
        Setting::setValue('grace_period_days', 0);
        $service = app(AccessBlockService::class);

        $clean = $this->subscription($this->customer());
        $overdue = $this->subscription($this->customer());
        $this->invoice($overdue, 'overdue', '2026-10-01');
        $due = $this->subscription($this->customer());
        $this->invoice($due, 'unpaid', '2026-10-25');
        $inactive = $this->subscription($this->customer(['status' => 'inactive']));
        $overridden = $this->subscription($this->customer(['access_override_until' => now()->addDays(3)]));
        $this->invoice($overridden, 'overdue', '2026-10-01');

        $subscriptions = Subscription::with('customer')->get();
        $map = $service->strictBlockMapForSubscriptions($subscriptions);

        foreach ($subscriptions as $subscription) {
            $this->assertSame(
                $service->isCustomerBlocked($subscription->customer, true, $subscription->id),
                $map[$subscription->customer_id.':'.$subscription->id],
                'Mismatch for subscription #'.$subscription->id
            );
        }

        $this->assertTrue($map[$overdue->customer_id.':'.$overdue->id]);
        $this->assertFalse($map[$clean->customer_id.':'.$clean->id]);
    }

    #[Test]
    public function the_license_list_does_not_query_invoices_per_license(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $count = function () use ($admin) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($admin)->get(route('admin.licenses.index'))->assertOk();
            $queries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], '"invoices"'))->count();
            DB::disableQueryLog();

            return $queries;
        };

        $this->license($this->subscription($this->customer()));
        $count(); // warm the cached header counts
        $few = $count();

        foreach (range(1, 5) as $i) {
            $this->license($this->subscription($this->customer()));
        }

        $this->assertSame($few, $count());
    }

    private function customer(array $extra = []): Customer
    {
        return Customer::create(array_merge(['name' => 'Follow-up '.uniqid(), 'status' => 'active'], $extra));
    }

    private function subscription(Customer $customer): Subscription
    {
        $product = Product::create(['name' => 'Follow-up Product', 'slug' => 'follow-'.uniqid(), 'status' => 'active']);
        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Monthly',
            'slug' => 'follow-plan-'.uniqid(),
            'interval' => 'monthly',
            'price' => 100,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        return Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => '2026-10-01',
            'current_period_start' => '2026-10-01',
            'current_period_end' => '2026-10-31',
            'next_invoice_at' => '2026-11-01',
            'auto_renew' => true,
            'cancel_at_period_end' => false,
        ]);
    }

    private function license(Subscription $subscription, array $extra = []): License
    {
        return License::create(array_merge([
            'subscription_id' => $subscription->id,
            'product_id' => $subscription->plan->product_id,
            'license_key' => strtoupper(uniqid('FOLLOW')),
            'status' => 'active',
            'starts_at' => '2026-10-01',
            'expires_at' => now()->addMonth()->toDateString(),
            'max_domains' => 1,
        ], $extra));
    }

    private function invoice(Subscription $subscription, string $status, string $dueDate): Invoice
    {
        return Invoice::create([
            'customer_id' => $subscription->customer_id,
            'subscription_id' => $subscription->id,
            'number' => 'INV-'.uniqid(),
            'status' => $status,
            'issue_date' => '2026-09-25',
            'due_date' => $dueDate,
            'subtotal' => 100,
            'late_fee' => 0,
            'total' => 100,
            'currency' => 'USD',
        ]);
    }
}
