<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\EmployeeAttendance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AttendanceController extends Controller
{
    public function index(Request $request): InertiaResponse
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

        return Inertia::render('Employee/Attendance/Index', [
            'attendances' => $attendances->getCollection()->map(function (EmployeeAttendance $attendance) {
                $dateFormat = config('app.date_format', 'd-m-Y');

                return [
                    'date_display' => $attendance->date?->format($dateFormat) ?? '--',
                    'status_label' => ucfirst(str_replace('_', ' ', (string) $attendance->status)),
                    'note' => $attendance->note ?? '--',
                    'recorder_name' => $attendance->recorder?->name ?? '--',
                    'updated_at_display' => $attendance->updated_at?->format(config('app.datetime_format', 'd-m-Y h:i A')) ?? '--',
                ];
            })->values()->all(),
            'selected_month' => $selectedMonth,
            'status_summary' => [
                'present' => (int) ($statusSummary['present'] ?? 0),
                'absent' => (int) ($statusSummary['absent'] ?? 0),
                'leave' => (int) ($statusSummary['leave'] ?? 0),
                'half_day' => (int) ($statusSummary['half_day'] ?? 0),
            ],
            'pagination' => [
                'current_page' => $attendances->currentPage(),
                'last_page' => $attendances->lastPage(),
                'per_page' => $attendances->perPage(),
                'total' => $attendances->total(),
                'from' => $attendances->firstItem(),
                'to' => $attendances->lastItem(),
                'prev_page_url' => $attendances->previousPageUrl(),
                'next_page_url' => $attendances->nextPageUrl(),
            ],
            'routes' => [
                'index' => route('employee.attendance.index'),
            ],
        ]);
    }
}
