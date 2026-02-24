<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\EmployeeWorkSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HrWorkLogsUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function work_logs_index_renders_direct_inertia_component_for_admin(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $employeeUser = User::factory()->create();
        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'name' => 'Work Log Employee',
            'email' => 'work-log-employee@example.test',
            'employment_type' => 'full_time',
            'work_mode' => 'on_site',
            'join_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        EmployeeWorkSession::create([
            'employee_id' => $employee->id,
            'work_date' => now()->toDateString(),
            'started_at' => now()->startOfDay()->addHours(9),
            'ended_at' => now()->startOfDay()->addHours(11),
            'last_activity_at' => now()->startOfDay()->addHours(11),
            'active_seconds' => 7200,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.hr.timesheets.index'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Hr\\/WorkLogs\\/Index', false);
    }

    #[Test]
    public function work_logs_index_remains_forbidden_for_client_role(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        $this->actingAs($client)
            ->get(route('admin.hr.timesheets.index'))
            ->assertForbidden();
    }
}
