<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\EmployeeWorkSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeWorkSessionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function part_time_remote_ping_counts_active_time_within_idle_cutoff(): void
    {
        Date::setTestNow(Carbon::parse('2026-04-02 09:00:00'));

        [$user, $employee] = $this->makeEmployee([
            'employment_type' => 'part_time',
            'work_mode' => 'remote',
        ], [
            'salary_type' => 'hourly',
            'basic_pay' => 50,
        ]);

        $this->actingAs($user, 'employee')
            ->post(route('employee.work-sessions.start'))
            ->assertOk();

        Date::setTestNow(Carbon::parse('2026-04-02 09:10:00'));

        $this->actingAs($user, 'employee')
            ->post(route('employee.work-sessions.ping'))
            ->assertOk();

        $session = EmployeeWorkSession::first();
        $this->assertSame(600, (int) $session->active_seconds);
    }

    #[Test]
    public function idle_gap_at_or_above_fifteen_minutes_is_not_counted(): void
    {
        Date::setTestNow(Carbon::parse('2026-04-02 09:00:00'));

        [$user] = $this->makeEmployee([
            'employment_type' => 'part_time',
            'work_mode' => 'remote',
        ]);

        $this->actingAs($user, 'employee')
            ->post(route('employee.work-sessions.start'))
            ->assertOk();

        Date::setTestNow(Carbon::parse('2026-04-02 09:20:00'));

        $this->actingAs($user, 'employee')
            ->post(route('employee.work-sessions.ping'))
            ->assertOk();

        $session = EmployeeWorkSession::first();
        $this->assertSame(0, (int) $session->active_seconds);

        Date::setTestNow(Carbon::parse('2026-04-02 09:25:00'));

        $this->actingAs($user, 'employee')
            ->post(route('employee.work-sessions.ping'))
            ->assertOk();

        $session->refresh();
        $this->assertSame(300, (int) $session->active_seconds);
    }

    #[Test]
    public function full_time_remote_summary_returns_required_seconds(): void
    {
        Date::setTestNow(Carbon::parse('2026-04-02 10:00:00'));

        [$user] = $this->makeEmployee([
            'employment_type' => 'full_time',
            'work_mode' => 'remote',
        ]);

        $response = $this->actingAs($user, 'employee')
            ->get(route('employee.work-summaries.today'));

        $response->assertOk();
        $response->assertJsonPath('data.required_seconds', 28800);
    }

    #[Test]
    public function daily_summary_generation_is_proportional_and_capped(): void
    {
        Date::setTestNow(Carbon::parse('2026-04-02 08:00:00'));

        [$user, $employee] = $this->makeEmployee([
            'employment_type' => 'part_time',
            'work_mode' => 'remote',
        ], [
            'salary_type' => 'hourly',
            'basic_pay' => 100,
        ]);

        EmployeeWorkSession::create([
            'employee_id' => $employee->id,
            'work_date' => '2026-04-01',
            'started_at' => '2026-04-01 09:00:00',
            'last_activity_at' => '2026-04-01 09:00:00',
            'active_seconds' => 21600,
        ]);

        $this->artisan('employee-work-summaries:generate', ['--date' => '2026-04-01'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('employee_work_summaries', [
            'employee_id' => $employee->id,
            'work_date' => '2026-04-01 00:00:00',
            'active_seconds' => 21600,
            'required_seconds' => 14400,
            'generated_salary_amount' => 400.00,
        ]);
    }

    #[Test]
    public function non_remote_employees_cannot_start_work_sessions(): void
    {
        Date::setTestNow(Carbon::parse('2026-04-02 11:00:00'));

        [$user] = $this->makeEmployee([
            'employment_type' => 'full_time',
            'work_mode' => 'on_site',
        ]);

        $this->actingAs($user, 'employee')
            ->post(route('employee.work-sessions.start'))
            ->assertStatus(403);
    }

    private function makeEmployee(array $employeeOverrides = [], array $compOverrides = []): array
    {
        $user = User::factory()->create();

        $employee = Employee::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Remote Employee',
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_mode' => 'remote',
        ], $employeeOverrides));

        EmployeeCompensation::create(array_merge([
            'employee_id' => $employee->id,
            'salary_type' => 'hourly',
            'currency' => 'BDT',
            'basic_pay' => 0,
            'effective_from' => now()->toDateString(),
            'is_active' => true,
        ], $compOverrides));

        return [$user, $employee];
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }
}
