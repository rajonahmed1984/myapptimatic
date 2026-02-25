<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Services\AutomationStatusService;
use App\Services\DashboardMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminDashboardUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_dashboard_renders_direct_inertia_component_with_expected_props(): void
    {
        $this->mock(AutomationStatusService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getStatusPayload')->once()->andReturn([
                'lastCompletionText' => 'Never',
                'statusLabel' => 'Pending',
                'statusClasses' => 'bg-slate-100 text-slate-600',
            ]);
        });

        $this->mock(DashboardMetricsService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getMetrics')->once()->andReturn([
                'counts' => [
                    'customerCount' => 0,
                    'productCount' => 0,
                    'subscriptionCount' => 0,
                    'licenseCount' => 0,
                    'pendingInvoiceCount' => 0,
                    'overdueCount' => 0,
                    'pendingOrderCount' => 0,
                    'openTicketCount' => 0,
                    'customerReplyTicketCount' => 0,
                ],
                'businessPulse' => [
                    'today_income' => 0,
                    'income_30d' => 0,
                    'previous_income_30d' => 0,
                    'expense_30d' => 0,
                    'net_30d' => 0,
                    'income_growth_percent' => null,
                    'pending_orders' => 0,
                    'unpaid_invoices' => 0,
                    'overdue_invoices' => 0,
                    'overdue_share_percent' => 0,
                    'open_tickets' => 0,
                    'customer_reply_tickets' => 0,
                    'support_load' => 0,
                    'health_score' => 0,
                    'health_label' => 'Unknown',
                    'health_classes' => 'bg-slate-100 text-slate-700',
                ],
                'automation' => [
                    'invoices_created' => 0,
                    'overdue_suspensions' => 0,
                    'tickets_closed' => 0,
                    'overdue_reminders' => 0,
                ],
                'automationRuns' => [],
                'automationMetrics' => [],
                'periodMetrics' => [
                    'today' => ['new_orders' => 0, 'active_orders' => 0, 'income' => 0, 'hosting_income' => 0],
                    'month' => ['new_orders' => 0, 'active_orders' => 0, 'income' => 0, 'hosting_income' => 0],
                    'year' => ['new_orders' => 0, 'active_orders' => 0, 'income' => 0, 'hosting_income' => 0],
                ],
                'periodSeries' => [
                    'today' => ['labels' => [], 'new_orders' => [], 'active_orders' => [], 'income' => []],
                    'month' => ['labels' => [], 'new_orders' => [], 'active_orders' => [], 'income' => []],
                    'year' => ['labels' => [], 'new_orders' => [], 'active_orders' => [], 'income' => []],
                ],
                'incomeStatement' => [],
                'billingAmounts' => ['today' => 0, 'month' => 0, 'year' => 0, 'all_time' => 0],
                'currency' => 'BDT',
                'clientActivity' => ['activeCount' => 0, 'onlineCount' => 0, 'recentClients' => []],
                'projectMaintenance' => ['projects_active' => 0, 'projects_on_hold' => 0, 'subscriptions_blocked' => 0, 'renewals_30d' => 0, 'projects_profitable' => 0, 'projects_loss' => 0],
                'hrStats' => ['active_employees' => 0, 'pending_timesheets' => 0, 'approved_timesheets' => 0, 'draft_payroll_periods' => 0, 'finalized_payroll_periods' => 0, 'payroll_items_to_pay' => 0],
            ]);
        });

        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.dashboard'));

        $response
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Dashboard', false);

        $props = $this->inertiaProps($response->getContent());

        $this->assertArrayHasKey('customerCount', $props);
        $this->assertArrayHasKey('businessPulse', $props);
        $this->assertArrayHasKey('periodMetrics', $props);
        $this->assertArrayHasKey('routes', $props);
        $this->assertSame(route('admin.customers.index'), data_get($props, 'routes.customers_index'));
        $this->assertSame(route('admin.automation-status'), data_get($props, 'routes.automation_status'));
    }

    #[Test]
    public function client_role_cannot_access_admin_dashboard(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        $this->actingAs($client)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function inertiaProps(string $html): array
    {
        preg_match('/data-page="([^"]+)"/', $html, $matches);
        $this->assertArrayHasKey(1, $matches, 'Inertia payload is missing in response.');

        $decoded = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        $payload = json_decode($decoded, true);
        $this->assertIsArray($payload);

        $props = data_get($payload, 'props', []);
        $this->assertIsArray($props);

        return $props;
    }
}
