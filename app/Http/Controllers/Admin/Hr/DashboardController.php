<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeWorkSession;
use App\Models\LeaveRequest;
use App\Models\PaidHoliday;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Services\EmployeeWorkSummaryService;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    public function __invoke(EmployeeWorkSummaryService $workSummaryService): InertiaResponse
    {
        $today = today();
        $todayDate = $today->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $trendStart = $today->copy()->subDays(13)->toDateString();

        $activeEmployees = Employee::query()->where('status', 'active')->count();
        $activeFullTimeEmployees = Employee::query()
            ->where('status', 'active')
            ->where('employment_type', 'full_time')
            ->count();

        $onLeaveToday = LeaveRequest::query()
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $todayDate)
            ->whereDate('end_date', '>=', $todayDate)
            ->distinct('employee_id')
            ->count('employee_id');

        $pendingLeaveRequests = LeaveRequest::query()->where('status', 'pending')->count();

        $attendanceMarkedToday = EmployeeAttendance::query()
            ->whereDate('date', $todayDate)
            ->count();
        $attendanceMissingToday = max(0, $activeFullTimeEmployees - $attendanceMarkedToday);

        $workLogsThisMonth = EmployeeWorkSession::query()
            ->whereBetween('work_date', [$monthStart, $monthEnd])
            ->selectRaw('employee_id, work_date')
            ->groupBy('employee_id', 'work_date')
            ->get();
        $workLogDaysThisMonth = $workLogsThisMonth->count();

        $workLogRows = EmployeeWorkSession::query()
            ->with('employee:id,employment_type,work_mode,status')
            ->whereBetween('work_date', [$monthStart, $monthEnd])
            ->selectRaw('employee_id, work_date, SUM(active_seconds) as active_seconds')
            ->groupBy('employee_id', 'work_date')
            ->get();

        $onTargetDaysThisMonth = $workLogRows->filter(function ($row) use ($workSummaryService) {
            if (! $row->employee || ! $workSummaryService->isEligible($row->employee)) {
                return false;
            }

            return (int) ($row->active_seconds ?? 0) >= $workSummaryService->requiredSeconds($row->employee);
        })->count();

        $draftPeriods = PayrollPeriod::query()->where('status', 'draft')->count();
        $finalizedPeriods = PayrollPeriod::query()->where('status', 'finalized')->count();
        $paidPeriods = PayrollPeriod::query()->where('status', 'paid')->count();

        $payrollToPay = PayrollItem::query()->whereIn('status', ['approved', 'partial'])->count();
        $paidPayrollItems = PayrollItem::query()->where('status', 'paid')->count();
        $paidHolidaysThisMonth = PaidHoliday::query()
            ->where('is_paid', true)
            ->whereBetween('holiday_date', [$monthStart, $monthEnd])
            ->count();

        $leaveStatusSummaryRaw = LeaveRequest::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get();
        $leaveSummary = [
            'pending' => (int) ($leaveStatusSummaryRaw->firstWhere('status', 'pending')->total ?? 0),
            'approved' => (int) ($leaveStatusSummaryRaw->firstWhere('status', 'approved')->total ?? 0),
            'rejected' => (int) ($leaveStatusSummaryRaw->firstWhere('status', 'rejected')->total ?? 0),
        ];

        $employmentMix = Employee::query()
            ->where('status', 'active')
            ->selectRaw('COALESCE(employment_type, "unknown") as type_key, COUNT(*) as total')
            ->groupBy('employment_type')
            ->get()
            ->map(fn ($row) => [
                'key' => (string) ($row->type_key ?? 'unknown'),
                'label' => ucfirst(str_replace('_', ' ', (string) ($row->type_key ?? 'unknown'))),
                'total' => (int) ($row->total ?? 0),
            ])
            ->values();

        $workModeMix = Employee::query()
            ->where('status', 'active')
            ->selectRaw('COALESCE(work_mode, "unknown") as mode_key, COUNT(*) as total')
            ->groupBy('work_mode')
            ->get()
            ->map(fn ($row) => [
                'key' => (string) ($row->mode_key ?? 'unknown'),
                'label' => ucfirst(str_replace('_', ' ', (string) ($row->mode_key ?? 'unknown'))),
                'total' => (int) ($row->total ?? 0),
            ])
            ->values();

        $attendanceTrendRaw = EmployeeAttendance::query()
            ->whereBetween('date', [$trendStart, $todayDate])
            ->selectRaw('date, COUNT(DISTINCT employee_id) as marked')
            ->groupBy('date')
            ->get()
            ->mapWithKeys(fn ($row) => [Carbon::parse((string) $row->date)->toDateString() => (int) ($row->marked ?? 0)]);

        $workTrendRaw = EmployeeWorkSession::query()
            ->whereBetween('work_date', [$trendStart, $todayDate])
            ->selectRaw('work_date, COUNT(DISTINCT employee_id) as logged_employees, SUM(active_seconds) as active_seconds')
            ->groupBy('work_date')
            ->get()
            ->mapWithKeys(fn ($row) => [
                Carbon::parse((string) $row->work_date)->toDateString() => [
                    'logged_employees' => (int) ($row->logged_employees ?? 0),
                    'active_seconds' => (int) ($row->active_seconds ?? 0),
                ],
            ]);

        $attendanceTrend = collect(range(13, 0))
            ->map(function (int $daysBack) use ($today, $attendanceTrendRaw, $activeFullTimeEmployees) {
                $date = $today->copy()->subDays($daysBack);
                $dateKey = $date->toDateString();
                $marked = (int) ($attendanceTrendRaw[$dateKey] ?? 0);
                $missing = max(0, $activeFullTimeEmployees - $marked);
                $coveragePercent = $activeFullTimeEmployees > 0
                    ? round(($marked / $activeFullTimeEmployees) * 100, 1)
                    : 0.0;

                return [
                    'date' => $dateKey,
                    'label' => $date->format('d M'),
                    'marked' => $marked,
                    'missing' => $missing,
                    'coverage_percent' => $coveragePercent,
                ];
            })
            ->values();

        $workTrend = collect(range(13, 0))
            ->map(function (int $daysBack) use ($today, $workTrendRaw) {
                $date = $today->copy()->subDays($daysBack);
                $dateKey = $date->toDateString();
                $row = $workTrendRaw[$dateKey] ?? ['logged_employees' => 0, 'active_seconds' => 0];
                $activeHours = round(((int) ($row['active_seconds'] ?? 0)) / 3600, 2);

                return [
                    'date' => $dateKey,
                    'label' => $date->format('d M'),
                    'logged_employees' => (int) ($row['logged_employees'] ?? 0),
                    'active_hours' => $activeHours,
                ];
            })
            ->values();

        $attendanceCoverageToday = $activeFullTimeEmployees > 0
            ? round(($attendanceMarkedToday / $activeFullTimeEmployees) * 100, 1)
            : 0.0;
        $onTargetRateThisMonth = $workLogDaysThisMonth > 0
            ? round(($onTargetDaysThisMonth / $workLogDaysThisMonth) * 100, 1)
            : 0.0;
        $leavePressure = $activeEmployees > 0
            ? round(($onLeaveToday / $activeEmployees) * 100, 1)
            : 0.0;
        $payrollPressure = max(0, $payrollToPay - $paidPayrollItems);

        $recentWorkLogs = EmployeeWorkSession::query()
            ->with('employee')
            ->orderByDesc('work_date')
            ->orderByDesc('last_activity_at')
            ->limit(8)
            ->get();

        $recentLeaveRequests = LeaveRequest::query()
            ->with(['employee', 'leaveType'])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        $recentAttendance = EmployeeAttendance::query()
            ->with(['employee', 'recorder'])
            ->orderByDesc('date')
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();

        return Inertia::render('Admin/Hr/Dashboard', [
            'pageTitle' => 'HR Dashboard',
            'activeEmployees' => $activeEmployees,
            'activeFullTimeEmployees' => $activeFullTimeEmployees,
            'onLeaveToday' => $onLeaveToday,
            'pendingLeaveRequests' => $pendingLeaveRequests,
            'attendanceMarkedToday' => $attendanceMarkedToday,
            'attendanceMissingToday' => $attendanceMissingToday,
            'workLogDaysThisMonth' => $workLogDaysThisMonth,
            'onTargetDaysThisMonth' => $onTargetDaysThisMonth,
            'onTargetRateThisMonth' => $onTargetRateThisMonth,
            'paidHolidaysThisMonth' => $paidHolidaysThisMonth,
            'draftPeriods' => $draftPeriods,
            'finalizedPeriods' => $finalizedPeriods,
            'paidPeriods' => $paidPeriods,
            'payrollToPay' => $payrollToPay,
            'paidPayrollItems' => $paidPayrollItems,
            'attendanceCoverageToday' => $attendanceCoverageToday,
            'leavePressure' => $leavePressure,
            'payrollPressure' => $payrollPressure,
            'leaveSummary' => $leaveSummary,
            'employmentMix' => $employmentMix,
            'workModeMix' => $workModeMix,
            'attendanceTrend' => $attendanceTrend,
            'workTrend' => $workTrend,
            'recentWorkLogs' => $recentWorkLogs->map(fn (EmployeeWorkSession $log) => [
                'employee_name' => $log->employee?->name ?? '--',
                'work_date' => $log->work_date?->format(config('app.date_format', 'd-m-Y')) ?? '--',
                'active_hours' => number_format(((int) ($log->active_seconds ?? 0)) / 3600, 2),
            ])->values(),
            'recentLeaveRequests' => $recentLeaveRequests->map(fn (LeaveRequest $leave) => [
                'employee_name' => $leave->employee?->name ?? '--',
                'leave_type' => $leave->leaveType?->name ?? 'Leave',
                'status' => ucfirst((string) $leave->status),
                'start_date' => $leave->start_date?->format(config('app.date_format', 'd-m-Y')) ?? '--',
            ])->values(),
            'recentAttendance' => $recentAttendance->map(fn (EmployeeAttendance $attendance) => [
                'employee_name' => $attendance->employee?->name ?? '--',
                'status' => ucfirst(str_replace('_', ' ', (string) $attendance->status)),
                'date' => $attendance->date?->format(config('app.date_format', 'd-m-Y')) ?? '--',
            ])->values(),
            'currentMonth' => Carbon::now()->format('Y-m'),
            'routes' => [
                'employeesCreate' => route('admin.hr.employees.create'),
                'attendanceIndex' => route('admin.hr.attendance.index'),
                'paidHolidaysIndex' => route('admin.hr.paid-holidays.index'),
                'payrollIndex' => route('admin.hr.payroll.index'),
                'employeesIndex' => route('admin.hr.employees.index'),
                'leaveRequestsIndex' => route('admin.hr.leave-requests.index'),
                'timesheetsIndex' => route('admin.hr.timesheets.index'),
            ],
        ]);
    }
}
