<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Timesheet;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $activeEmployees = Employee::query()->where('status', 'active')->count();
        $draftPeriods = PayrollPeriod::query()->where('status', 'draft')->count();
        $pendingTimesheets = Timesheet::query()->whereIn('status', ['submitted', 'approved'])->count();

        return view('admin.hr.dashboard', [
            'activeEmployees' => $activeEmployees,
            'draftPeriods' => $draftPeriods,
            'pendingTimesheets' => $pendingTimesheets,
        ]);
    }
}
