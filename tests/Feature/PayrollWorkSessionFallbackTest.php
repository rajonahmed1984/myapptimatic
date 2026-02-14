<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\EmployeeWorkSession;
use App\Models\PaidHoliday;
use App\Models\PayrollItem;
use App\Models\User;
use App\Services\EmployeeWorkSummaryService;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollWorkSessionFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_hourly_payroll_uses_work_session_hours_when_timesheet_is_missing(): void
    {
        $employee = $this->createHourlyRemoteEmployee(100);

        EmployeeWorkSession::create([
            'employee_id' => $employee->id,
            'work_date' => '2026-05-10',
            'started_at' => '2026-05-10 09:00:00',
            'last_activity_at' => '2026-05-10 11:00:00',
            'ended_at' => '2026-05-10 11:00:00',
            'active_seconds' => 7200,
        ]);

        app(PayrollService::class)->generatePeriod('2026-05');

        $item = PayrollItem::query()->where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame(2.0, (float) $item->timesheet_hours);
        $this->assertSame(200.0, (float) $item->gross_pay);
        $this->assertSame(200.0, (float) $item->net_pay);
    }

    public function test_hourly_payroll_ignores_legacy_timesheet_hours_when_present(): void
    {
        $employee = $this->createHourlyRemoteEmployee(100);

        EmployeeWorkSession::create([
            'employee_id' => $employee->id,
            'work_date' => '2026-05-10',
            'started_at' => '2026-05-10 09:00:00',
            'last_activity_at' => '2026-05-10 14:00:00',
            'ended_at' => '2026-05-10 14:00:00',
            'active_seconds' => 18000,
        ]);

        app(PayrollService::class)->generatePeriod('2026-05');

        $item = PayrollItem::query()->where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame(5.0, (float) $item->timesheet_hours);
        $this->assertSame(500.0, (float) $item->gross_pay);
    }

    public function test_hourly_payroll_adds_paid_holiday_hours_even_without_sessions(): void
    {
        $employee = $this->createHourlyRemoteEmployee(100);

        PaidHoliday::create([
            'holiday_date' => '2026-05-01',
            'name' => 'May Day',
            'is_paid' => true,
        ]);

        app(PayrollService::class)->generatePeriod('2026-05');

        $item = PayrollItem::query()->where('employee_id', $employee->id)->firstOrFail();

        // part_time remote employee => 4h paid holiday credit.
        $this->assertSame(4.0, (float) $item->timesheet_hours);
        $this->assertSame(400.0, (float) $item->gross_pay);
        $this->assertSame(400.0, (float) $item->net_pay);
    }

    public function test_work_summary_returns_full_daily_amount_on_paid_holiday(): void
    {
        $employee = $this->createHourlyRemoteEmployee(100);

        PaidHoliday::create([
            'holiday_date' => '2026-05-02',
            'name' => 'Weekend Holiday',
            'is_paid' => true,
        ]);

        $amount = app(EmployeeWorkSummaryService::class)->calculateAmount(
            $employee->fresh('activeCompensation'),
            Carbon::parse('2026-05-02'),
            0
        );

        // part_time hourly => 4 hours * rate 100.
        $this->assertSame(400.0, $amount);
    }

    private function createHourlyRemoteEmployee(float $rate): Employee
    {
        $user = User::factory()->create();

        $employee = Employee::create([
            'user_id' => $user->id,
            'name' => 'Payroll Remote',
            'email' => 'payroll-remote-' . uniqid() . '@example.test',
            'status' => 'active',
            'employment_type' => 'part_time',
            'work_mode' => 'remote',
            'join_date' => '2026-01-01',
        ]);

        EmployeeCompensation::create([
            'employee_id' => $employee->id,
            'salary_type' => 'hourly',
            'currency' => 'BDT',
            'basic_pay' => $rate,
            'overtime_rate' => $rate,
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);

        return $employee;
    }
}
