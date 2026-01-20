<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeSession;
use App\Models\User;
use App\Models\UserSession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SessionClosureTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function stale_user_sessions_are_closed(): void
    {
        config()->set('session.lifetime', 10);
        Carbon::setTestNow(Carbon::parse('2026-01-20 12:00:00'));

        $user = User::factory()->create();

        $staleSession = UserSession::create([
            'user_type' => User::class,
            'user_id' => $user->id,
            'guard' => 'web',
            'session_id' => 'stale-session',
            'login_at' => now()->subHours(2),
            'logout_at' => null,
            'last_seen_at' => now()->subMinutes(40),
            'active_seconds' => 600,
        ]);

        $freshSession = UserSession::create([
            'user_type' => User::class,
            'user_id' => $user->id,
            'guard' => 'web',
            'session_id' => 'fresh-session',
            'login_at' => now()->subMinutes(10),
            'logout_at' => null,
            'last_seen_at' => now()->subMinutes(5),
            'active_seconds' => 120,
        ]);

        $this->artisan('user-sessions:close-stale')
            ->assertExitCode(0);

        $staleSession->refresh();
        $freshSession->refresh();

        $this->assertNotNull($staleSession->logout_at);
        $this->assertSame($staleSession->last_seen_at?->toDateTimeString(), $staleSession->logout_at?->toDateTimeString());
        $this->assertNull($freshSession->logout_at);
    }

    #[Test]
    public function stale_employee_sessions_are_closed(): void
    {
        config()->set('session.lifetime', 10);
        Carbon::setTestNow(Carbon::parse('2026-01-20 13:00:00'));

        $employee = Employee::create([
            'name' => 'Session Employee',
            'status' => 'active',
        ]);

        $staleSession = EmployeeSession::create([
            'employee_id' => $employee->id,
            'session_id' => 'emp-stale',
            'login_at' => now()->subHours(2),
            'logout_at' => null,
            'last_seen_at' => now()->subMinutes(40),
            'active_seconds' => 400,
        ]);

        $freshSession = EmployeeSession::create([
            'employee_id' => $employee->id,
            'session_id' => 'emp-fresh',
            'login_at' => now()->subMinutes(10),
            'logout_at' => null,
            'last_seen_at' => now()->subMinutes(5),
            'active_seconds' => 120,
        ]);

        $this->artisan('employee-sessions:close-stale')
            ->assertExitCode(0);

        $staleSession->refresh();
        $freshSession->refresh();

        $this->assertNotNull($staleSession->logout_at);
        $this->assertNull($freshSession->logout_at);
    }
}
