<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\EmployeeAttendance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $selectedMonth = $validated['month'] ?? now()->format('Y-m');
        [$year, $month] = explode('-', $selectedMonth);
        $startDate = Carbon::createFromDate((int) $year, (int) $month, 1)->startOfMonth()->toDateString();
        $endDate = Carbon::createFromDate((int) $year, (int) $month, 1)->endOfMonth()->toDateString();

        $employee = $request->attributes->get('employee');

        $attendances = EmployeeAttendance::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->with('recorder:id,name')
            ->orderByDesc('date')
            ->paginate(31)
            ->withQueryString();

        $statusSummary = $attendances->getCollection()
            ->groupBy('status')
            ->map(fn ($rows) => $rows->count());

        return view('employee.attendance.index', [
            'attendances' => $attendances,
            'selectedMonth' => $selectedMonth,
            'statusSummary' => $statusSummary,
        ]);
    }
}

