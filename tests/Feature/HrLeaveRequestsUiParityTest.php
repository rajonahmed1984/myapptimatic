<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HrLeaveRequestsUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function leave_requests_index_renders_direct_inertia_component_for_admin(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $employeeUser = User::factory()->create();
        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'name' => 'Leave Employee',
            'email' => 'leave-employee@example.test',
            'employment_type' => 'full_time',
            'work_mode' => 'on_site',
            'join_date' => now()->toDateString(),
            'status' => 'active',
        ]);
        $leaveType = LeaveType::create([
            'name' => 'Annual Leave',
            'code' => 'AL',
            'is_paid' => true,
            'default_allocation' => 14,
        ]);
        LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'total_days' => 2,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.hr.leave-requests.index'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Hr\\/LeaveRequests\\/Index', false);
    }

    #[Test]
    public function leave_request_approve_and_reject_contracts_are_preserved(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $employeeUser = User::factory()->create();
        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'name' => 'Leave Contract Employee',
            'email' => 'leave-contract@example.test',
            'employment_type' => 'full_time',
            'work_mode' => 'on_site',
            'join_date' => now()->toDateString(),
            'status' => 'active',
        ]);
        $leaveType = LeaveType::create([
            'name' => 'Sick Leave',
            'code' => 'SL',
            'is_paid' => true,
            'default_allocation' => 10,
        ]);

        $leave = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'total_days' => 2,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.hr.leave-requests.approve', $leave))
            ->assertRedirect()
            ->assertSessionHas('status', 'Leave request approved.');

        $leave->update(['status' => 'pending']);

        $this->actingAs($admin)
            ->post(route('admin.hr.leave-requests.reject', $leave), [
                'reason' => 'Policy mismatch',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Leave request rejected.');
    }

    #[Test]
    public function leave_requests_index_remains_forbidden_for_client_role(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        $this->actingAs($client)
            ->get(route('admin.hr.leave-requests.index'))
            ->assertForbidden();
    }
}
