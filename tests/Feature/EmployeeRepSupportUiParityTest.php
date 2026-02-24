<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
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
}
