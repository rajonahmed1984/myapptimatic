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
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(EmployeeWorkSummaryService $workSummaryService): View
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

        return view('admin.hr.dashboard', [
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
            'recentWorkLogs' => $recentWorkLogs,
            'recentLeaveRequests' => $recentLeaveRequests,
            'recentAttendance' => $recentAttendance,
            'currentMonth' => Carbon::now()->format('Y-m'),
        ]);
    }
}
