<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeActivityDaily;
use App\Models\EmployeeSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeActivityTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    #[Test]
    public function employee_login_creates_session_and_daily_row(): void
    {
        Carbon::setTestNow($now = Carbon::parse('2026-01-14 09:00:00'));

        $user = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => bcrypt('password'),
        ]);

        $employee = Employee::create([
            'user_id' => $user->id,
            'name' => 'Login Tester',
            'status' => 'active',
        ]);

        $response = $this->post('/employee/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('employee.dashboard'));

        $this->assertDatabaseHas('employee_sessions', [
            'employee_id' => $employee->id,
            'logout_at' => null,
        ]);

        $this->assertDatabaseHas('employee_activity_dailies', [
            'employee_id' => $employee->id,
            'date' => $now->toDateString(),
            'sessions_count' => 1,
        ]);
    }

    #[Test]
    public function activity_tracking_middleware_detects_and_records_session(): void
    {
        Carbon::setTestNow($now = Carbon::parse('2026-01-14 10:00:00'));

        $user = User::factory()->create();
        $employee = Employee::create([
            'user_id' => $user->id,
            'name' => 'Activity User',
            'status' => 'active',
        ]);

        $priorLoginTime = $now->copy()->subMinutes(2);

        $session = EmployeeSession::create([
            'employee_id' => $employee->id,
            'session_id' => 'test-session-123',
            'login_at' => $priorLoginTime,
            'last_seen_at' => $priorLoginTime,
            'active_seconds' => 0,
        ]);

        EmployeeActivityDaily::create([
            'employee_id' => $employee->id,
            'date' => $priorLoginTime->toDateString(),
            'sessions_count' => 1,
            'active_seconds' => 0,
            'first_login_at' => $priorLoginTime,
            'last_seen_at' => $priorLoginTime,
        ]);

        $this->assertTrue($session->exists);
        $this->assertEquals(0, $session->active_seconds);
    }

    #[Test]
    public function employee_is_online_when_recently_seen_and_not_logged_out(): void
    {
        Carbon::setTestNow($now = Carbon::parse('2026-01-14 11:00:00'));

        $employee = Employee::create([
            'user_id' => User::factory()->create()->id,
            'name' => 'Presence Test',
            'status' => 'active',
        ]);

        EmployeeSession::create([
            'employee_id' => $employee->id,
            'session_id' => 'sess-123',
            'login_at' => $now->copy()->subMinutes(5),
            'last_seen_at' => $now->copy()->subMinute(),
            'active_seconds' => 0,
        ]);

        $this->assertTrue($employee->isOnline());

        EmployeeSession::query()->where('employee_id', $employee->id)->update(['logout_at' => $now]);
        $this->assertFalse($employee->isOnline());

        EmployeeSession::query()->update(['logout_at' => null, 'last_seen_at' => $now->copy()->subMinutes(10)]);
        $this->assertFalse($employee->isOnline());
    }

    #[Test]
    public function admin_summary_aggregates_today_week_month_and_range(): void
    {
        Carbon::setTestNow($now = Carbon::parse('2026-01-14 12:00:00'));

        $admin = User::factory()->create(['role' => 'master_admin']);

        $employeeA = Employee::create([
            'user_id' => User::factory()->create()->id,
            'name' => 'Alice Analyst',
            'status' => 'active',
        ]);

        $employeeB = Employee::create([
            'user_id' => User::factory()->create()->id,
            'name' => 'Bob Builder',
            'status' => 'active',
        ]);

        // Employee A data
        EmployeeActivityDaily::insert([
            [
                'employee_id' => $employeeA->id,
                'date' => $now->toDateString(),
                'sessions_count' => 2,
                'active_seconds' => 3600,
                'first_login_at' => $now->copy()->startOfDay(),
                'last_seen_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'employee_id' => $employeeA->id,
                'date' => $now->copy()->subDays(2)->toDateString(),
                'sessions_count' => 1,
                'active_seconds' => 900,
                'first_login_at' => $now->copy()->subDays(2),
                'last_seen_at' => $now->copy()->subDays(2)->endOfDay(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'employee_id' => $employeeA->id,
                'date' => $now->copy()->subDays(10)->toDateString(),
                'sessions_count' => 1,
                'active_seconds' => 600,
                'first_login_at' => $now->copy()->subDays(10),
                'last_seen_at' => $now->copy()->subDays(10),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // Employee B data
        EmployeeActivityDaily::create([
            'employee_id' => $employeeB->id,
            'date' => $now->toDateString(),
            'sessions_count' => 1,
            'active_seconds' => 300,
            'first_login_at' => $now->copy()->startOfDay(),
            'last_seen_at' => $now,
        ]);

        EmployeeSession::create([
            'employee_id' => $employeeA->id,
            'session_id' => 'sess-a',
            'login_at' => $now->copy()->subHours(3),
            'last_seen_at' => $now,
            'active_seconds' => 0,
        ]);

        EmployeeSession::create([
            'employee_id' => $employeeB->id,
            'session_id' => 'sess-b',
            'login_at' => $now->copy()->subHours(2),
            'last_seen_at' => $now->copy()->subMinutes(1),
            'active_seconds' => 0,
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.employees.summary', [
                'from' => $now->copy()->subDays(2)->toDateString(),
                'to' => $now->toDateString(),
            ]));

        $response->assertOk();

        $employees = $response->viewData('employees');

        $alice = $employees->firstWhere('id', $employeeA->id);
        $this->assertNotNull($alice);
        $this->assertSame(2, (int) ($alice->today_sessions_count ?? 0));
        $this->assertSame(3600, (int) ($alice->today_active_seconds ?? 0));
        $this->assertSame(3, (int) ($alice->week_sessions_count ?? 0));
        $this->assertSame(4500, (int) ($alice->week_active_seconds ?? 0));
        $this->assertSame(4, (int) ($alice->month_sessions_count ?? 0));
        $this->assertSame(5100, (int) ($alice->month_active_seconds ?? 0));
        $this->assertSame(3, (int) ($alice->range_sessions_count ?? 0));
        $this->assertSame(4500, (int) ($alice->range_active_seconds ?? 0));

        $bob = $employees->firstWhere('id', $employeeB->id);
        $this->assertNotNull($bob);
        $this->assertSame(1, (int) ($bob->today_sessions_count ?? 0));
        $this->assertSame(300, (int) ($bob->today_active_seconds ?? 0));
    }
}
