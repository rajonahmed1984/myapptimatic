<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\EmployeeWorkSession;
use App\Models\LeaveRequest;
use App\Models\PaidHoliday;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Support\SystemLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    /**
     * Create or load a payroll period and draft payroll items.
     */
    public function generatePeriod(string $periodKey): PayrollPeriod
    {
        [$year, $month] = explode('-', $periodKey);
        $start = Carbon::createFromDate((int) $year, (int) $month, 1)->startOfDay();
        $end = (clone $start)->endOfMonth();

        return DB::transaction(function () use ($periodKey, $start, $end) {
            $period = PayrollPeriod::query()->firstOrCreate(
                ['period_key' => $periodKey],
                [
                    'start_date' => $start,
                    'end_date' => $end,
                    'status' => 'draft',
                ]
            );

            // Skip if already generated
            if ($period->payrollItems()->exists()) {
                return $period;
            }

            $employees = Employee::query()
                ->where('status', 'active')
                ->with('activeCompensation')
                ->get();

            foreach ($employees as $employee) {
                $comp = $employee->activeCompensation;
                if (! $comp instanceof EmployeeCompensation) {
                    continue;
                }

                $payType = $comp->salary_type ?? 'monthly';
                $currency = $comp->currency ?? 'BDT';
                $basePay = (float) ($comp->basic_pay ?? 0);
                $timesheetHours = $payType === 'hourly'
                    ? $this->workSessionHours($employee, $period->start_date, $period->end_date)
                    : 0.0;

                $gross = $payType === 'hourly'
                    ? $this->computeHourlyGross($timesheetHours, (float) ($comp->overtime_rate ?? $basePay))
                    : $this->computeMonthlyGross($employee, $comp, $period->start_date, $period->end_date);

                PayrollItem::create([
                    'payroll_period_id' => $period->id,
                    'employee_id' => $employee->id,
                    'status' => 'draft',
                    'pay_type' => $payType,
                    'currency' => $currency,
                    'base_pay' => $basePay,
                    'timesheet_hours' => $timesheetHours,
                    'overtime_enabled' => (bool) ($comp->overtime_enabled ?? false),
                    'gross_pay' => $gross,
                    'net_pay' => $gross,
                ]);
            }

            SystemLogger::write('payroll', 'Payroll calculated.', [
                'period_id' => $period->id,
                'period_key' => $period->period_key,
                'items_created' => $period->payrollItems()->count(),
            ]);

            return $period;
        });
    }

    /**
     * Compute pro-rated monthly salary if joined or exited mid-period, and adjust for unpaid leave.
     */
    public function computeProRata(
        float $monthlySalary,
        $joinDate,
        Carbon $periodStart,
        Carbon $periodEnd,
        ?Carbon $exitDate = null,
        float $unpaidLeaveDays = 0.0
    ): float
    {
        $join = $joinDate ? Carbon::parse($joinDate) : $periodStart;
        $start = $join->lessThan($periodStart) ? $periodStart : $join;

        $end = $exitDate instanceof Carbon
            ? ($exitDate->lessThan($periodEnd) ? $exitDate : $periodEnd)
            : $periodEnd;

        if ($start->greaterThan($end)) {
            return 0.0;
        }

        $totalDaysInPeriod = $periodStart->diffInDays($periodEnd) + 1;
        $eligibleDays = $start->diffInDays($end) + 1;
        $workingDays = max(0, $eligibleDays - $unpaidLeaveDays);

        $dailyRate = $totalDaysInPeriod > 0 ? $monthlySalary / $totalDaysInPeriod : $monthlySalary;

        return $this->roundMoney($dailyRate * $workingDays);
    }

    /**
     * Compute hourly pay from timesheet hours and rate.
     */
    public function computeHourly(float $hours, float $rate): float
    {
        return $this->roundMoney($hours * $rate);
    }

    /**
     * Apply manual adjustments and recalculate gross/net.
     */
    public function applyAdjustments(PayrollItem $item, array $adjustments = []): PayrollItem
    {
        $bonuses = (float) ($adjustments['bonuses'] ?? 0);
        $penalties = (float) ($adjustments['penalties'] ?? 0);
        $advances = (float) ($adjustments['advances'] ?? 0);
        $deductions = (float) ($adjustments['deductions'] ?? 0);
        $overtimeHours = (float) ($adjustments['overtime_hours'] ?? 0);
        $overtimeRate = (float) ($adjustments['overtime_rate'] ?? $item->overtime_rate ?? 0);

        $allowOvertime = (bool) ($item->overtime_enabled ?? false);
        if (! $allowOvertime) {
            $overtimeHours = 0.0;
            $overtimeRate = 0.0;
        }

        $overtimePay = $allowOvertime && $overtimeHours > 0
            ? $this->computeHourly($overtimeHours, $overtimeRate)
            : 0;

        $gross = (float) $item->gross_pay + $bonuses + $overtimePay;
        $net = $gross - $penalties - $deductions - $advances;

        $gross = $this->roundMoney($gross);
        $net = $this->roundMoney($net);

        $item->update([
            'overtime_hours' => $overtimeHours,
            'overtime_rate' => $overtimeRate,
            'bonuses' => $bonuses,
            'penalties' => $penalties,
            'advances' => $advances,
            'deductions' => $deductions,
            'gross_pay' => $gross,
            'net_pay' => $net,
        ]);

        return $item;
    }

    /**
     * Compute the monthly gross pay including proration and unpaid-leave deductions.
     */
    private function computeMonthlyGross(Employee $employee, EmployeeCompensation $comp, Carbon $periodStart, Carbon $periodEnd): float
    {
        $baseMonthly = (float) ($comp->basic_pay ?? 0);
        $allowances = $this->sumArray($comp->allowances);
        $compDeductions = $this->sumArray($comp->deductions);

        $exitDate = $employee->exit_date ? Carbon::parse($employee->exit_date) : null;
        $unpaidLeaveDays = $this->unpaidLeaveDays($employee->id, $periodStart, $periodEnd);

        $baseGross = $this->computeProRata(
            $baseMonthly,
            $employee->join_date,
            $periodStart,
            $periodEnd,
            $exitDate,
            $unpaidLeaveDays
        );

        $gross = $baseGross + $allowances - $compDeductions;

        return $this->roundMoney(max(0, $gross));
    }

    /**
     * Finalize a period: mark items approved, lock totals.
     */
    public function finalizePeriod(PayrollPeriod $period): PayrollPeriod
    {
        DB::transaction(function () use ($period) {
            $period->payrollItems()->update(['status' => 'approved', 'locked_at' => now()]);
            $period->update(['status' => 'finalized', 'finalized_at' => now()]);

            SystemLogger::write('payroll', 'Payroll finalized.', [
                'period_id' => $period->id,
                'period_key' => $period->period_key,
            ]);
        });

        return $period->fresh('payrollItems');
    }

    /**
     * Mark an item as paid with reference.
     */
    public function markPaid(PayrollItem $item, string $reference = null): PayrollItem
    {
        $item->update([
            'status' => 'paid',
            'payment_reference' => $reference,
            'paid_at' => now(),
        ]);

        SystemLogger::write('payroll', 'Payroll item marked paid.', [
            'payroll_item_id' => $item->id,
            'employee_id' => $item->employee_id,
            'reference' => $reference,
        ]);

        return $item;
    }

    /**
     * Fallback hours from work sessions for eligible remote full-time/part-time employees.
     */
    private function workSessionHours(Employee $employee, Carbon $start, Carbon $end): float
    {
        if (! in_array($employee->employment_type, ['full_time', 'part_time'], true)
            || $employee->work_mode !== 'remote') {
            return 0.0;
        }

        $secondsByDate = EmployeeWorkSession::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $end->toDateString())
            ->selectRaw('work_date, SUM(active_seconds) as total_seconds')
            ->groupBy('work_date')
            ->pluck('total_seconds', 'work_date');

        $seconds = (int) $secondsByDate->sum();

        $requiredSecondsPerDay = $employee->employment_type === 'part_time'
            ? 4 * 3600
            : 8 * 3600;

        if ($requiredSecondsPerDay > 0) {
            $holidayDates = PaidHoliday::query()
                ->where('is_paid', true)
                ->whereBetween('holiday_date', [$start->toDateString(), $end->toDateString()])
                ->pluck('holiday_date');

            foreach ($holidayDates as $holidayDate) {
                $holidayKey = Carbon::parse((string) $holidayDate)->toDateString();
                $loggedSeconds = (int) ($secondsByDate[$holidayKey] ?? 0);
                if ($loggedSeconds < $requiredSecondsPerDay) {
                    $seconds += ($requiredSecondsPerDay - $loggedSeconds);
                }
            }
        }

        if ($seconds <= 0) {
            return 0.0;
        }

        return $this->roundMoney($seconds / 3600);
    }

    /**
     * Count unpaid leave days within the payroll window (approved + unpaid types only).
     */
    private function unpaidLeaveDays(int $employeeId, Carbon $periodStart, Carbon $periodEnd): float
    {
        $paidHolidayDates = PaidHoliday::query()
            ->where('is_paid', true)
            ->whereBetween('holiday_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->pluck('holiday_date')
            ->map(fn ($date) => Carbon::parse((string) $date)->toDateString())
            ->flip();

        $requests = LeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereDate('end_date', '>=', $periodStart)
            ->whereDate('start_date', '<=', $periodEnd)
            ->with('leaveType')
            ->get();

        $days = 0.0;
        foreach ($requests as $request) {
            if ($request->leaveType && $request->leaveType->is_paid) {
                continue;
            }
            $start = Carbon::parse($request->start_date)->lessThan($periodStart) ? $periodStart : Carbon::parse($request->start_date);
            $end = Carbon::parse($request->end_date)->greaterThan($periodEnd) ? $periodEnd : Carbon::parse($request->end_date);
            if ($start->greaterThan($end)) {
                continue;
            }

            $cursor = $start->copy()->startOfDay();
            $last = $end->copy()->startOfDay();
            while ($cursor->lessThanOrEqualTo($last)) {
                if (! isset($paidHolidayDates[$cursor->toDateString()])) {
                    $days += 1;
                }
                $cursor->addDay();
            }
        }

        return $days;
    }

    private function sumArray($value): float
    {
        if (! is_array($value)) {
            return 0.0;
        }

        return (float) array_reduce($value, function ($carry, $item) {
            return $carry + (float) ($item['amount'] ?? $item ?? 0);
        }, 0.0);
    }

    private function computeHourlyGross(float $hours, float $rate): float
    {
        return $this->roundMoney($hours * $rate);
    }

    private function roundMoney(float $amount): float
    {
        return round($amount, 2, PHP_ROUND_HALF_UP);
    }
}
