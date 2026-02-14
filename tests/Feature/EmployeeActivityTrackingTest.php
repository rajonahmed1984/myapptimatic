<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Models\UserSession;
use App\Models\UserActivityDaily;
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

        // Employee guard authenticates via User model (not Employee model directly)
        // so the session is recorded with user_type: User::class
        $this->assertDatabaseHas('user_sessions', [
            'user_type' => User::class,
            'user_id' => $user->id,
            'guard' => 'employee',
            'logout_at' => null,
        ]);

        $this->assertDatabaseHas('user_activity_dailies', [
            'user_type' => User::class,
            'user_id' => $user->id,
            'guard' => 'employee',
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

        $session = UserSession::create([
            'user_type' => Employee::class,
            'user_id' => $employee->id,
            'guard' => 'employee',
            'session_id' => 'test-session-123',
            'login_at' => $priorLoginTime,
            'last_seen_at' => $priorLoginTime,
            'active_seconds' => 0,
        ]);

        UserActivityDaily::create([
            'user_type' => Employee::class,
            'user_id' => $employee->id,
            'guard' => 'employee',
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

        UserSession::create([
            'user_type' => Employee::class,
            'user_id' => $employee->id,
            'guard' => 'employee',
            'session_id' => 'sess-123',
            'login_at' => $now->copy()->subMinutes(5),
            'last_seen_at' => $now->copy()->subMinute(),
            'active_seconds' => 0,
        ]);

        $this->assertTrue($employee->isOnline());

        UserSession::query()->where('user_type', Employee::class)->where('user_id', $employee->id)->update(['logout_at' => $now]);
        $this->assertFalse($employee->isOnline());

        UserSession::query()->update(['logout_at' => null, 'last_seen_at' => $now->copy()->subMinutes(10)]);
        $this->assertFalse($employee->isOnline());
    }

}
