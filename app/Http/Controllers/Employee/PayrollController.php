<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\EmployeeAttendance;
use App\Models\EmployeePayout;
use App\Models\EmployeeWorkSession;
use App\Models\PaidHoliday;
use App\Models\PayrollItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PayrollController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $employee = $request->attributes->get('employee');

        $items = PayrollItem::query()
            ->where('payroll_items.employee_id', $employee->id)
            ->leftJoin('payroll_periods', 'payroll_periods.id', '=', 'payroll_items.payroll_period_id')
            ->with('period')
            ->orderByDesc('payroll_periods.start_date')
            ->orderByDesc('payroll_periods.period_key')
            ->orderByDesc('payroll_items.id')
            ->select('payroll_items.*')
            ->paginate(15);

        $paidAmountByItem = [];
        $grossByItem = [];
        $bonusByItem = [];
        $penaltyByItem = [];
        $advancePaidByItem = [];
        $deductionByItem = [];
        $netPayableByItem = [];
        $remainingByItem = [];
        $statusLabelByItem = [];
        $periodWindows = [];

        foreach ($items as $item) {
            $periodKey = (string) ($item->period?->period_key ?? '');
            $periodStart = $item->period?->start_date?->toDateString();
            $periodEnd = $item->period?->end_date?->toDateString();
            if ($periodKey !== '' && $periodStart && $periodEnd) {
                $periodWindows[$periodKey] = [
                    'start' => $periodStart,
                    'end' => $periodEnd,
                ];
            }
        }

        $coordinatedAdvanceByPeriod = [];
        $periodStatsByKey = [];
        if (! empty($periodWindows)) {
            $periodKeys = array_keys($periodWindows);
            $windowStart = collect($periodWindows)->pluck('start')->min();
            $windowEnd = collect($periodWindows)->pluck('end')->max();

            $advancePayouts = EmployeePayout::query()
                ->where('employee_id', $employee->id)
                ->where('metadata->type', 'advance')
                ->where('metadata->advance_scope', 'payroll')
                ->whereNotNull('paid_at')
                ->where(function ($query) use ($periodKeys, $windowStart, $windowEnd) {
                    $query->whereIn('metadata->coordination_month', $periodKeys)
                        ->orWhere(function ($legacyQuery) use ($windowStart, $windowEnd) {
                            $legacyQuery->where(function ($nullOrEmptyQuery) {
                                $nullOrEmptyQuery->whereNull('metadata->coordination_month')
                                    ->orWhere('metadata->coordination_month', '');
                            })->whereDate('paid_at', '>=', $windowStart)
                                ->whereDate('paid_at', '<=', $windowEnd);
                        });
                })
                ->get(['amount', 'paid_at', 'metadata']);

            foreach ($periodKeys as $periodKey) {
                $coordinatedAdvanceByPeriod[$periodKey] = 0.0;
            }

            foreach ($advancePayouts as $advancePayout) {
                $metadata = is_array($advancePayout->metadata) ? $advancePayout->metadata : [];
                $coordinationMonth = (string) ($metadata['coordination_month'] ?? '');
                $amount = round((float) ($advancePayout->amount ?? 0), 2);

                if ($coordinationMonth !== '' && array_key_exists($coordinationMonth, $coordinatedAdvanceByPeriod)) {
                    $coordinatedAdvanceByPeriod[$coordinationMonth] = round(
                        (float) $coordinatedAdvanceByPeriod[$coordinationMonth] + $amount,
                        2
                    );
                    continue;
                }

                $paidAt = $advancePayout->paid_at?->toDateString();
                if (! $paidAt) {
                    continue;
                }

                foreach ($periodWindows as $periodKey => $window) {
                    if ($paidAt >= $window['start'] && $paidAt <= $window['end']) {
                        $coordinatedAdvanceByPeriod[$periodKey] = round(
                            (float) $coordinatedAdvanceByPeriod[$periodKey] + $amount,
                            2
                        );
                        break;
                    }
                }
            }

            foreach ($periodWindows as $periodKey => $window) {
                $startDate = $window['start'];
                $endDate = $window['end'];

                $paidHolidayDates = PaidHoliday::query()
                    ->where('is_paid', true)
                    ->whereBetween('holiday_date', [$startDate, $endDate])
                    ->pluck('holiday_date')
                    ->map(fn ($date) => Carbon::parse((string) $date)->toDateString())
                    ->all();
                $paidHolidayMap = array_fill_keys($paidHolidayDates, true);

                $workingDays = 0;
                $cursor = Carbon::parse($startDate)->startOfDay();
                $periodEnd = Carbon::parse($endDate)->startOfDay();
                while ($cursor->lessThanOrEqualTo($periodEnd)) {
                    if (! isset($paidHolidayMap[$cursor->toDateString()])) {
                        $workingDays++;
                    }
                    $cursor->addDay();
                }

                $totalWorkSeconds = (int) EmployeeWorkSession::query()
                    ->where('employee_id', $employee->id)
                    ->whereDate('work_date', '>=', $startDate)
                    ->whereDate('work_date', '<=', $endDate)
                    ->when(! empty($paidHolidayDates), fn ($q) => $q->whereNotIn('work_date', $paidHolidayDates))
                    ->sum('active_seconds');
                $workLogHours = round($totalWorkSeconds / 3600, 2);

                $attendanceRows = EmployeeAttendance::query()
                    ->where('employee_id', $employee->id)
                    ->whereDate('date', '>=', $startDate)
                    ->whereDate('date', '<=', $endDate)
                    ->when(! empty($paidHolidayDates), fn ($q) => $q->whereNotIn('date', $paidHolidayDates))
                    ->get(['status']);
                $presentDays = (float) $attendanceRows->where('status', 'present')->count();
                $halfDays = (float) $attendanceRows->where('status', 'half_day')->count() * 0.5;
                $workedDays = round($presentDays + $halfDays, 1);

                $periodStatsByKey[$periodKey] = [
                    'work_log_hours' => $workLogHours,
                    'worked_days' => $workedDays,
                    'working_days' => $workingDays,
                ];
            }
        }

        $employmentType = (string) ($employee->employment_type ?? '');

        foreach ($items as $item) {
            $paidAmount = round((float) ($item->paid_amount ?? 0), 2);
            $bonus = round($this->sumAdjustment($item->bonuses), 2);
            $penalty = round($this->sumAdjustment($item->penalties), 2);
            $periodKey = (string) ($item->period?->period_key ?? '');
            $hasPeriodStats = array_key_exists($periodKey, $periodStatsByKey);
            $periodStats = $periodStatsByKey[$periodKey] ?? [
                'work_log_hours' => 0.0,
                'worked_days' => 0.0,
                'working_days' => 0,
            ];
            $isHoursBased = $item->pay_type === 'hourly' || $employmentType === 'part_time';
            $isAttendanceBased = $employmentType === 'full_time';
            $hoursPerDay = $employmentType === 'part_time' ? 4 : 8;
            $actualHours = (float) ($periodStats['work_log_hours'] ?? 0);
            if ($actualHours <= 0) {
                $actualHours = (float) ($item->timesheet_hours ?? 0);
            }
            $workedDays = (float) ($periodStats['worked_days'] ?? 0);
            $workingDays = (int) ($periodStats['working_days'] ?? 0);
            $expectedHours = max(0, $workingDays) * $hoursPerDay;
            $basePay = (float) ($item->base_pay ?? 0);
            $estSubtotal = $basePay;
            if ($hasPeriodStats) {
                if ($isHoursBased) {
                    $hoursRatio = $expectedHours > 0 ? min(1, max(0, $actualHours / $expectedHours)) : 0;
                    $estSubtotal = $basePay * $hoursRatio;
                } elseif ($isAttendanceBased && $workingDays > 0) {
                    $attendanceRatio = min(1, max(0, $workedDays / $workingDays));
                    $estSubtotal = $basePay * $attendanceRatio;
                }
            }
            $estSubtotal = round($estSubtotal, 2);
            $storedAdvance = round($this->sumAdjustment($item->advances), 2);
            $fallbackAdvance = round((float) ($coordinatedAdvanceByPeriod[$periodKey] ?? 0), 2);
            $effectiveAdvance = $storedAdvance > 0 ? $storedAdvance : $fallbackAdvance;
            $advancePaidByItem[$item->id] = $effectiveAdvance;
            $deduction = round($this->sumAdjustment($item->deductions), 2);
            $overtimePay = (float) ($item->overtime_hours ?? 0) * (float) ($item->overtime_rate ?? 0);
            $computedGross = round($estSubtotal + $overtimePay + $bonus, 2);
            $computedNet = round($computedGross - $penalty - $effectiveAdvance - $deduction, 2);
            $netPayable = round(max(0, $computedNet), 2);

            $paidAmountByItem[$item->id] = $paidAmount;
            $grossByItem[$item->id] = $computedGross;
            $bonusByItem[$item->id] = $bonus;
            $penaltyByItem[$item->id] = $penalty;
            $deductionByItem[$item->id] = $deduction;
            $netPayableByItem[$item->id] = $netPayable;
            $remainingByItem[$item->id] = round(max(0, $netPayable - $paidAmount), 2);
            $statusLabelByItem[$item->id] = ucfirst((string) ($netPayable <= 0 ? 'paid' : $item->status));
        }

        return Inertia::render('Employee/Payroll/Index', [
            'items' => $items->getCollection()->map(function (PayrollItem $item) use (
                $paidAmountByItem,
                $grossByItem,
                $bonusByItem,
                $penaltyByItem,
                $advancePaidByItem,
                $deductionByItem,
                $netPayableByItem,
                $remainingByItem,
                $statusLabelByItem
            ) {
                return [
                    'id' => $item->id,
                    'period_key' => $item->period?->period_key ?? '--',
                    'gross_pay' => (float) ($grossByItem[$item->id] ?? 0),
                    'bonus' => (float) ($bonusByItem[$item->id] ?? 0),
                    'penalty' => (float) ($penaltyByItem[$item->id] ?? 0),
                    'advance' => (float) ($advancePaidByItem[$item->id] ?? 0),
                    'deduction' => (float) ($deductionByItem[$item->id] ?? 0),
                    'net_payable' => (float) ($netPayableByItem[$item->id] ?? 0),
                    'paid' => (float) ($paidAmountByItem[$item->id] ?? 0),
                    'remaining' => (float) ($remainingByItem[$item->id] ?? 0),
                    'status_label' => (string) ($statusLabelByItem[$item->id] ?? ucfirst((string) $item->status)),
                    'paid_at_display' => $item->paid_at?->format(config('app.datetime_format', 'd-m-Y h:i A')) ?? '--',
                    'currency' => $item->currency,
                ];
            })->values()->all(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
                'prev_page_url' => $items->previousPageUrl(),
                'next_page_url' => $items->nextPageUrl(),
            ],
        ]);
    }

    private function sumAdjustment(mixed $value): float
    {
        if (is_array($value)) {
            return (float) array_sum(array_map(
                fn ($row) => (float) (is_array($row) ? ($row['amount'] ?? 0) : $row),
                $value
            ));
        }

        return (float) ($value ?? 0);
    }
}
