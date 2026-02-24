<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\PaidHoliday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AttendanceController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $selectedDate = $validated['date'] ?? now()->toDateString();
        $isPaidHoliday = PaidHoliday::query()
            ->whereDate('holiday_date', $selectedDate)
            ->where('is_paid', true)
            ->exists();

        $employees = Employee::query()
            ->where('status', 'active')
            ->where('employment_type', 'full_time')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'department', 'designation']);

        $attendanceByEmployee = EmployeeAttendance::query()
            ->with('recorder:id,name')
            ->whereDate('date', $selectedDate)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->keyBy('employee_id');

        return Inertia::render('Admin/Hr/Attendance/Index', [
            'pageTitle' => 'Attendance',
            'selectedDate' => $selectedDate,
            'isPaidHoliday' => $isPaidHoliday,
            'employees' => $employees->map(function (Employee $employee) use ($attendanceByEmployee, $isPaidHoliday) {
                $entry = $attendanceByEmployee->get($employee->id);

                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'department' => $employee->department ?? '--',
                    'designation' => $employee->designation ?? '--',
                    'status' => $entry?->status ?? ($isPaidHoliday ? 'present' : null),
                    'note' => $entry?->note,
                    'recorder_name' => $entry?->recorder?->name,
                    'updated_at' => $entry?->updated_at?->format('Y-m-d H:i'),
                ];
            })->values(),
            'routes' => [
                'index' => route('admin.hr.attendance.index'),
                'store' => route('admin.hr.attendance.store'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'records' => ['nullable', 'array'],
            'records.*.employee_id' => ['required', 'integer', 'exists:employees,id'],
            'records.*.status' => ['nullable', 'in:present,absent,leave,half_day'],
            'records.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        $date = $validated['date'];
        $isPaidHoliday = PaidHoliday::query()
            ->whereDate('holiday_date', $date)
            ->where('is_paid', true)
            ->exists();
        $records = $validated['records'] ?? [];
        $allowedEmployeeIds = Employee::query()
            ->where('status', 'active')
            ->where('employment_type', 'full_time')
            ->whereIn('id', collect($records)->pluck('employee_id'))
            ->pluck('id')
            ->all();

        foreach ($records as $record) {
            $employeeId = (int) $record['employee_id'];
            if (! in_array($employeeId, $allowedEmployeeIds, true)) {
                continue;
            }

            $status = $record['status'] ?? null;
            if ($isPaidHoliday && ! $status) {
                $status = 'present';
            }
            $note = isset($record['note']) ? trim((string) $record['note']) : null;
            $note = $note !== '' ? $note : null;

            if (! $status) {
                EmployeeAttendance::query()
                    ->where('employee_id', $employeeId)
                    ->whereDate('date', $date)
                    ->delete();

                continue;
            }

            EmployeeAttendance::query()->updateOrCreate(
                [
                    'employee_id' => $employeeId,
                    'date' => $date,
                ],
                [
                    'status' => $status,
                    'note' => $note,
                    'recorded_by' => $request->user()?->id,
                ]
            );
        }

        return back()->with('status', 'Attendance updated for '.$date.'.');
    }
}
