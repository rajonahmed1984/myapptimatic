<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\Project;
use App\Models\SalesRepresentative;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeRepSupportUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function employee_portal_routes_render_inertia_pages(): void
    {
        $customer = Customer::create(['name' => 'Employee Portal Customer']);

        $user = User::factory()->create(['role' => 'employee']);
        $employee = Employee::create([
            'user_id' => $user->id,
            'name' => 'Employee Portal User',
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_mode' => 'remote',
            'join_date' => now()->toDateString(),
        ]);

        $project = Project::create([
            'name' => 'Employee Portal Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);
        $project->employees()->sync([$employee->id]);

        $this->actingAs($user, 'employee')
            ->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee('Employee\\/Dashboard\\/Index', false);

        $this->actingAs($user, 'employee')
            ->get(route('employee.tasks.index'))
            ->assertOk()
            ->assertSee('Employee\\/Tasks\\/Index', false);

        $this->actingAs($user, 'employee')
            ->get(route('employee.chats.index'))
            ->assertOk()
            ->assertSee('Employee\\/Chats\\/Index', false);

        $this->actingAs($user, 'employee')
            ->get(route('employee.profile.edit'))
            ->assertOk()
            ->assertSee('Employee\\/Profile\\/Edit', false);

        $this->actingAs($user, 'employee')
            ->get(route('employee.timesheets.index'))
            ->assertOk()
            ->assertSee('Employee\\/Timesheets\\/Index', false);

        $this->actingAs($user, 'employee')
            ->get(route('employee.leave-requests.index'))
            ->assertOk()
            ->assertSee('Employee\\/LeaveRequests\\/Index', false);

        $this->actingAs($user, 'employee')
            ->get(route('employee.attendance.index'))
            ->assertOk()
            ->assertSee('Employee\\/Attendance\\/Index', false);

        $this->actingAs($user, 'employee')
            ->get(route('employee.payroll.index'))
            ->assertOk()
            ->assertSee('Employee\\/Payroll\\/Index', false);

        $this->actingAs($user, 'employee')
            ->get(route('employee.projects.index'))
            ->assertOk()
            ->assertSee('Employee\\/Projects\\/Index', false);

        $this->actingAs($user, 'employee')
            ->get(route('employee.projects.show', $project))
            ->assertOk()
            ->assertSee('Employee\\/Projects\\/Show', false);
    }

    #[Test]
    public function sales_rep_portal_routes_render_inertia_pages(): void
    {
        $customer = Customer::create(['name' => 'Sales Portal Customer']);

        $user = User::factory()->create(['role' => 'sales']);
        $rep = SalesRepresentative::create([
            'user_id' => $user->id,
            'name' => 'Sales Portal User',
            'status' => 'active',
        ]);

        $project = Project::create([
            'name' => 'Sales Portal Project',
            'customer_id' => $customer->id,
            'type' => 'software',
            'status' => 'ongoing',
            'total_budget' => 1000,
            'initial_payment_amount' => 100,
            'currency' => 'USD',
        ]);
        $project->salesRepresentatives()->sync([$rep->id]);

        $this->actingAs($user, 'sales')
            ->get(route('rep.dashboard'))
            ->assertOk()
            ->assertSee('Rep\\/Dashboard\\/Index', false);

        $this->actingAs($user, 'sales')
            ->get(route('rep.tasks.index'))
            ->assertOk()
            ->assertSee('Rep\\/Tasks\\/Index', false);

        $this->actingAs($user, 'sales')
            ->get(route('rep.chats.index'))
            ->assertOk()
            ->assertSee('Rep\\/Chats\\/Index', false);

        $this->actingAs($user, 'sales')
            ->get(route('rep.profile.edit'))
            ->assertOk()
            ->assertSee('Rep\\/Profile\\/Edit', false);

        $this->actingAs($user, 'sales')
            ->get(route('rep.earnings.index'))
            ->assertOk()
            ->assertSee('Rep\\/Earnings\\/Index', false);

        $this->actingAs($user, 'sales')
            ->get(route('rep.payouts.index'))
            ->assertOk()
            ->assertSee('Rep\\/Payouts\\/Index', false);

        $this->actingAs($user, 'sales')
            ->get(route('rep.projects.index'))
            ->assertOk()
            ->assertSee('Rep\\/Projects\\/Index', false);

        $this->actingAs($user, 'sales')
            ->get(route('rep.projects.show', $project))
            ->assertOk()
            ->assertSee('Rep\\/Projects\\/Show', false);
    }

    #[Test]
    public function support_portal_routes_render_inertia_pages(): void
    {
        $support = User::factory()->create(['role' => 'support']);

        $customer = Customer::create(['name' => 'Support Portal Customer']);
        $ticket = SupportTicket::create([
            'customer_id' => $customer->id,
            'subject' => 'Support Portal Ticket',
            'status' => 'open',
            'priority' => 'medium',
            'last_reply_at' => now(),
        ]);

        $this->actingAs($support, 'support')
            ->get(route('support.dashboard'))
            ->assertOk()
            ->assertSee('Support\\/Dashboard\\/Index', false);

        $this->actingAs($support, 'support')
            ->get(route('support.support-tickets.index'))
            ->assertOk()
            ->assertSee('Support\\/SupportTickets\\/Index', false);

        $this->actingAs($support, 'support')
            ->get(route('support.support-tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Support\\/SupportTickets\\/Show', false);
    }

    #[Test]
    public function employee_payroll_is_sorted_by_latest_period_first(): void
    {
        $user = User::factory()->create(['role' => 'employee']);
        $employee = Employee::create([
            'user_id' => $user->id,
            'name' => 'Employee Payroll Sort',
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_mode' => 'remote',
            'join_date' => now()->toDateString(),
        ]);

        $periodJan = PayrollPeriod::create([
            'period_key' => '2026-01',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'finalized',
        ]);
        $periodMar = PayrollPeriod::create([
            'period_key' => '2026-03',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'status' => 'finalized',
        ]);
        $periodFeb = PayrollPeriod::create([
            'period_key' => '2026-02',
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-28',
            'status' => 'finalized',
        ]);

        PayrollItem::create([
            'payroll_period_id' => $periodJan->id,
            'employee_id' => $employee->id,
            'status' => 'approved',
            'pay_type' => 'monthly',
            'currency' => 'BDT',
            'net_pay' => 10000,
        ]);
        PayrollItem::create([
            'payroll_period_id' => $periodMar->id,
            'employee_id' => $employee->id,
            'status' => 'approved',
            'pay_type' => 'monthly',
            'currency' => 'BDT',
            'net_pay' => 12000,
        ]);
        PayrollItem::create([
            'payroll_period_id' => $periodFeb->id,
            'employee_id' => $employee->id,
            'status' => 'approved',
            'pay_type' => 'monthly',
            'currency' => 'BDT',
            'net_pay' => 11000,
        ]);

        $response = $this->actingAs($user, 'employee')
            ->get(route('employee.payroll.index'))
            ->assertOk();

        $props = $this->inertiaPayload($response->getContent());
        $periodKeys = collect((array) data_get($props, 'props.items'))
            ->pluck('period_key')
            ->take(3)
            ->values()
            ->all();

        $this->assertSame(['2026-03', '2026-02', '2026-01'], $periodKeys);
    }

    /**
     * @return array<string, mixed>
     */
    private function inertiaPayload(string $html): array
    {
        preg_match('/data-page="([^"]+)"/', $html, $matches);
        $this->assertArrayHasKey(1, $matches, 'Inertia payload is missing in response.');

        $decoded = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        $payload = json_decode($decoded, true);
        $this->assertIsArray($payload);

        return $payload;
    }
}
