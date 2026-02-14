<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeAttendanceFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_store_daily_attendance_for_full_time_employees_only(): void
    {
        $admin = User::factory()->create(['role' => 'master_admin']);

        $fullTimeUser = User::factory()->create();
        $fullTimeEmployee = Employee::create([
            'user_id' => $fullTimeUser->id,
            'name' => 'Full Time Employee',
            'email' => 'full-time@example.test',
            'employment_type' => 'full_time',
            'work_mode' => 'on_site',
            'join_date' => '2026-01-01',
            'status' => 'active',
        ]);

        $partTimeUser = User::factory()->create();
        $partTimeEmployee = Employee::create([
            'user_id' => $partTimeUser->id,
            'name' => 'Part Time Employee',
            'email' => 'part-time@example.test',
            'employment_type' => 'part_time',
            'work_mode' => 'on_site',
            'join_date' => '2026-01-01',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.hr.attendance.store'), [
            'date' => '2026-04-16',
            'records' => [
                [
                    'employee_id' => $fullTimeEmployee->id,
                    'status' => 'present',
                    'note' => 'On time',
                ],
                [
                    'employee_id' => $partTimeEmployee->id,
                    'status' => 'present',
                    'note' => 'Should be ignored',
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertTrue(
            EmployeeAttendance::query()
                ->where('employee_id', $fullTimeEmployee->id)
                ->whereDate('date', '2026-04-16')
                ->where('status', 'present')
                ->where('note', 'On time')
                ->where('recorded_by', $admin->id)
                ->exists()
        );

        $this->assertFalse(
            EmployeeAttendance::query()
                ->where('employee_id', $partTimeEmployee->id)
                ->whereDate('date', '2026-04-16')
                ->exists()
        );
    }

    public function test_employee_can_view_monthly_attendance_details(): void
    {
        $admin = User::factory()->create(['role' => 'master_admin']);
        $employeeUser = User::factory()->create(['role' => 'employee']);
        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'name' => 'Attendance Employee',
            'email' => 'attendance-employee@example.test',
            'employment_type' => 'full_time',
            'work_mode' => 'on_site',
            'join_date' => '2026-01-01',
            'status' => 'active',
        ]);

        EmployeeAttendance::create([
            'employee_id' => $employee->id,
            'date' => '2026-04-10',
            'status' => 'present',
            'note' => 'Came early',
            'recorded_by' => $admin->id,
        ]);

        EmployeeAttendance::create([
            'employee_id' => $employee->id,
            'date' => '2026-04-11',
            'status' => 'leave',
            'note' => 'Medical leave',
            'recorded_by' => $admin->id,
        ]);

        EmployeeAttendance::create([
            'employee_id' => $employee->id,
            'date' => '2026-03-05',
            'status' => 'absent',
            'note' => 'Previous month',
            'recorded_by' => $admin->id,
        ]);

        $response = $this->actingAs($employeeUser, 'employee')->get(route('employee.attendance.index', [
            'month' => '2026-04',
        ]));

        $response->assertOk();
        $response->assertSee('Attendance Details');
        $response->assertSee('Came early');
        $response->assertSee('Medical leave');
        $response->assertDontSee('Previous month');
    }
}
