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
        $activeEmployees = Employee::query()->where('status', 'active')->count();
        $activeFullTimeEmployees = Employee::query()
            ->where('status', 'active')
            ->where('employment_type', 'full_time')
            ->count();

        $onLeaveToday = LeaveRequest::query()
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->distinct('employee_id')
            ->count('employee_id');

        $pendingLeaveRequests = LeaveRequest::query()->where('status', 'pending')->count();

        $today = today()->toDateString();
        $attendanceMarkedToday = EmployeeAttendance::query()
            ->whereDate('date', $today)
            ->count();
        $attendanceMissingToday = max(0, $activeFullTimeEmployees - $attendanceMarkedToday);

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

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
        $payrollToPay = PayrollItem::query()->where('status', 'approved')->count();
        $paidHolidaysThisMonth = PaidHoliday::query()
            ->where('is_paid', true)
            ->whereBetween('holiday_date', [$monthStart, $monthEnd])
            ->count();

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
            'paidHolidaysThisMonth' => $paidHolidaysThisMonth,
            'draftPeriods' => $draftPeriods,
            'payrollToPay' => $payrollToPay,
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
