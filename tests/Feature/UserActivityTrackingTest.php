<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Customer;
use App\Models\User;
use App\Models\UserSession;
use App\Models\UserActivityDaily;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserActivityTrackingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that user sessions table stores data correctly.
     */
    public function test_user_sessions_table_stores_data()
    {
        $user = User::create([
            'name' => 'User Session',
            'email' => 'session-user@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        // Create a user session record
        $session = UserSession::create([
            'user_type' => User::class,
            'user_id' => $user->id,
            'guard' => 'web',
            'session_id' => 'test-session-123',
            'login_at' => now(),
            'logout_at' => null,
            'last_seen_at' => now(),
            'active_seconds' => 0,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'TestAgent',
        ]);

        $this->assertDatabaseHas('user_sessions', [
            'user_type' => User::class,
            'user_id' => $user->id,
            'guard' => 'web',
            'session_id' => 'test-session-123',
        ]);

        $this->assertNull($session->logout_at);
    }

    /**
     * Test that daily activity table stores aggregates correctly.
     */
    public function test_user_activity_daily_table_stores_data()
    {
        $user = User::create([
            'name' => 'User Daily',
            'email' => 'daily-user@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        // Create a daily activity record
        $daily = UserActivityDaily::create([
            'user_type' => User::class,
            'user_id' => $user->id,
            'guard' => 'web',
            'date' => now()->toDateString(),
            'sessions_count' => 2,
            'active_seconds' => 3600,
            'first_login_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->assertDatabaseHas('user_activity_dailies', [
            'user_type' => User::class,
            'user_id' => $user->id,
            'guard' => 'web',
            'date' => now()->toDateString(),
            'sessions_count' => 2,
        ]);

        $this->assertEquals(3600, $daily->active_seconds);
    }

    /**
     * Test that employee session relationships work.
     */
    public function test_employee_polymorphic_sessions_relationship()
    {
        // Create employee
        $employee = Employee::create([
            'name' => 'Test Employee',
            'email' => 'emp@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        // Create sessions for the employee using polymorphic UserSession
        $session1 = UserSession::create([
            'user_type' => Employee::class,
            'user_id' => $employee->id,
            'guard' => 'employee',
            'session_id' => 'sess-1',
            'login_at' => now(),
            'logout_at' => null,
            'last_seen_at' => now(),
            'active_seconds' => 100,
        ]);

        $session2 = UserSession::create([
            'user_type' => Employee::class,
            'user_id' => $employee->id,
            'guard' => 'employee',
            'session_id' => 'sess-2',
            'login_at' => now()->subHours(1),
            'logout_at' => now()->subMinutes(30),
            'last_seen_at' => now()->subMinutes(30),
            'active_seconds' => 1000,
        ]);

        // Reload employee
        $employee->refresh();

        // Check relationship
        $this->assertCount(2, $employee->activitySessions);
        $this->assertEquals($session2->id, $employee->activitySessions()->latest('id')->first()->id);
    }

    /**
     * Test that isOnline() method works correctly.
     */
    public function test_employee_is_online_with_recent_session()
    {
        // Create employee
        $employee = Employee::create([
            'name' => 'Test Employee',
            'email' => 'emp@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        // Not online initially
        $this->assertFalse($employee->isOnline());

        // Create recent session using polymorphic UserSession
        UserSession::create([
            'user_type' => Employee::class,
            'user_id' => $employee->id,
            'guard' => 'employee',
            'session_id' => 'test-sess',
            'login_at' => now()->subMinutes(1),
            'logout_at' => null,
            'last_seen_at' => now()->subMinutes(1),
            'active_seconds' => 60,
        ]);

        // Reload and check
        $employee->refresh();
        $this->assertTrue($employee->isOnline());
    }

    /**
     * Test that isOnline() respects the time window.
     */
    public function test_employee_is_online_respects_time_window()
    {
        $employee = Employee::create([
            'name' => 'Test Employee',
            'email' => 'emp@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        // Create session with old last_seen using polymorphic UserSession
        UserSession::create([
            'user_type' => Employee::class,
            'user_id' => $employee->id,
            'guard' => 'employee',
            'session_id' => 'old-sess',
            'login_at' => now()->subHours(2),
            'logout_at' => null,
            'last_seen_at' => now()->subMinutes(15),
            'active_seconds' => 600,
        ]);

        $employee->refresh();

        // Should be offline with 2-minute window
        $this->assertFalse($employee->isOnline(2));

        // Should be online with 20-minute window
        $this->assertTrue($employee->isOnline(20));
    }

    /**
     * Test daily activity aggregation for a user.
     */
    public function test_daily_activity_aggregation()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $today = now()->toDateString();

        // Create multiple daily records across time periods
        UserActivityDaily::create([
            'user_type' => User::class,
            'user_id' => $user->id,
            'guard' => 'web',
            'date' => $today,
            'sessions_count' => 3,
            'active_seconds' => 3600,
            'first_login_at' => now(),
            'last_seen_at' => now(),
        ]);

        UserActivityDaily::create([
            'user_type' => User::class,
            'user_id' => $user->id,
            'guard' => 'web',
            'date' => now()->subDays(2)->toDateString(),
            'sessions_count' => 2,
            'active_seconds' => 2400,
            'first_login_at' => now()->subDays(2),
            'last_seen_at' => now()->subDays(2),
        ]);

        // Query aggregates
        $weekData = UserActivityDaily::where('user_type', User::class)
            ->where('user_id', $user->id)
            ->where('guard', 'web')
            ->whereBetween('date', [now()->subDays(7)->toDateString(), $today])
            ->selectRaw('SUM(sessions_count) as total_sessions, SUM(active_seconds) as total_active_seconds')
            ->first();

        $this->assertEquals(5, $weekData->total_sessions); // 3 + 2
        $this->assertEquals(6000, $weekData->total_active_seconds); // 3600 + 2400
    }

    /**
     * Test polymorphic relationships for customers.
     */
    public function test_customer_polymorphic_sessions_relationship()
    {
        // Create customer and user
        $customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'cust@test.com',
            'status' => 'active',
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
            'role' => 'client',
            'customer_id' => $customer->id,
        ]);

        // Create session for user (users are tracked via User model, not Customer)
        UserSession::create([
            'user_type' => User::class,
            'user_id' => $user->id,
            'guard' => 'web',
            'session_id' => 'cust-sess',
            'login_at' => now(),
            'logout_at' => null,
            'last_seen_at' => now(),
            'active_seconds' => 300,
        ]);

        $user->refresh();
        $this->assertCount(1, $user->activitySessions);
    }

    /**
     * Test closing stale sessions via update.
     */
    public function test_session_can_be_closed()
    {
        $session = UserSession::create([
            'user_type' => Employee::class,
            'user_id' => 1,
            'guard' => 'employee',
            'session_id' => 'close-test',
            'login_at' => now()->subHours(3),
            'logout_at' => null,
            'last_seen_at' => now()->subHours(3),
            'active_seconds' => 1800,
        ]);

        $this->assertNull($session->logout_at);

        // Close the session
        $session->update(['logout_at' => now()]);

        $this->assertNotNull($session->logout_at);

        // Verify in database
        $this->assertDatabaseHas('user_sessions', [
            'session_id' => 'close-test',
            'logout_at' => $session->logout_at,
        ]);
    }

    /**
     * Test activity tracking supports multiple user types.
     */
    public function test_activity_tracking_supports_multiple_user_types()
    {
        // Employee
        $employee = Employee::create([
            'name' => 'Emp',
            'email' => 'emp@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        // Admin user
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        // Create sessions for both using polymorphic UserSession
        UserSession::create([
            'user_type' => Employee::class,
            'user_id' => $employee->id,
            'guard' => 'employee',
            'session_id' => 'emp-sess',
            'login_at' => now(),
            'logout_at' => null,
            'last_seen_at' => now(),
            'active_seconds' => 100,
        ]);

        UserSession::create([
            'user_type' => User::class,
            'user_id' => $admin->id,
            'guard' => 'web',
            'session_id' => 'admin-sess',
            'login_at' => now(),
            'logout_at' => null,
            'last_seen_at' => now(),
            'active_seconds' => 50,
        ]);

        // Verify separate tracking
        $empSessions = $employee->activitySessions;
        $this->assertCount(1, $empSessions);
        $adminSessions = $admin->activitySessions;
        $this->assertCount(1, $adminSessions);

        // Both employee and admin sessions should be UserSession instances (polymorphic)
        $this->assertInstanceOf(UserSession::class, $empSessions->first());
        $this->assertInstanceOf(UserSession::class, $adminSessions->first());
    }
}
