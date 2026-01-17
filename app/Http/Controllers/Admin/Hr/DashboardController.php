<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\Timesheet;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $activeEmployees = Employee::query()->where('status', 'active')->count();
        $newHires30 = Employee::query()
            ->whereNotNull('join_date')
            ->whereDate('join_date', '>=', now()->subDays(30))
            ->count();
        $onLeaveToday = LeaveRequest::query()
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->distinct('employee_id')
            ->count('employee_id');
        $pendingLeaveRequests = LeaveRequest::query()->where('status', 'pending')->count();

        $pendingTimesheets = Timesheet::query()->where('status', 'submitted')->count();
        $approvedTimesheets = Timesheet::query()->where('status', 'approved')->count();
        $lockedTimesheets = Timesheet::query()->where('status', 'locked')->count();

        $draftPeriods = PayrollPeriod::query()->where('status', 'draft')->count();
        $finalizedPeriods = PayrollPeriod::query()->where('status', 'finalized')->count();
        $payrollToPay = PayrollItem::query()->where('status', 'approved')->count();

        $recentTimesheets = Timesheet::query()
            ->with('employee')
            ->whereIn('status', ['submitted', 'approved', 'locked'])
            ->orderByDesc('submitted_at')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();
        $recentLeaveRequests = LeaveRequest::query()
            ->with(['employee', 'leaveType'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('admin.hr.dashboard', [
            'activeEmployees' => $activeEmployees,
            'newHires30' => $newHires30,
            'onLeaveToday' => $onLeaveToday,
            'pendingLeaveRequests' => $pendingLeaveRequests,
            'draftPeriods' => $draftPeriods,
            'finalizedPeriods' => $finalizedPeriods,
            'payrollToPay' => $payrollToPay,
            'pendingTimesheets' => $pendingTimesheets,
            'approvedTimesheets' => $approvedTimesheets,
            'lockedTimesheets' => $lockedTimesheets,
            'recentTimesheets' => $recentTimesheets,
            'recentLeaveRequests' => $recentLeaveRequests,
        ]);
    }
}
