<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HrAttendanceUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function attendance_index_renders_direct_inertia_component_for_admin(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $employeeUser = User::factory()->create();
        Employee::create([
            'user_id' => $employeeUser->id,
            'name' => 'Attendance UI User',
            'email' => 'attendance-ui@example.test',
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_mode' => 'on_site',
            'join_date' => now()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.hr.attendance.index'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Hr\\/Attendance\\/Index', false);
    }

    #[Test]
    public function attendance_store_contract_is_preserved(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $employeeUser = User::factory()->create();
        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'name' => 'Attendance Store User',
            'email' => 'attendance-store@example.test',
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_mode' => 'on_site',
            'join_date' => now()->toDateString(),
        ]);

        $date = now()->toDateString();

        $this->actingAs($admin)
            ->post(route('admin.hr.attendance.store'), [
                'date' => $date,
                'records' => [
                    [
                        'employee_id' => $employee->id,
                        'status' => 'present',
                        'note' => 'Parity check',
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('status', "Attendance updated for {$date}.");
    }

    #[Test]
    public function attendance_index_remains_forbidden_for_client_role(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        $this->actingAs($client)
            ->get(route('admin.hr.attendance.index'))
            ->assertForbidden();
    }
}
