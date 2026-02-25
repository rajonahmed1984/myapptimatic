<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeWorkSession;
use App\Models\PaidHoliday;
use App\Services\EmployeeWorkSummaryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TimesheetController extends Controller
{
    public function index(Request $request, EmployeeWorkSummaryService $workSummaryService): InertiaResponse
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ]);

        $selectedMonth = $filters['month'] ?? now()->format('Y-m');
        $selectedEmployeeId = isset($filters['employee_id']) ? (int) $filters['employee_id'] : null;

        [$year, $month] = explode('-', $selectedMonth);
        $monthStart = Carbon::createFromDate((int) $year, (int) $month, 1)->startOfMonth()->toDateString();
        $monthEnd = Carbon::createFromDate((int) $year, (int) $month, 1)->endOfMonth()->toDateString();

        $dailyLogs = EmployeeWorkSession::query()
            ->with([
                'employee:id,name,status,employment_type,work_mode',
                'employee.activeCompensation' => function ($query) {
                    $query->select([
                        'employee_compensations.id',
                        'employee_compensations.employee_id',
                        'employee_compensations.salary_type',
                        'employee_compensations.currency',
                        'employee_compensations.basic_pay',
                        'employee_compensations.overtime_rate',
                        'employee_compensations.allowances',
                        'employee_compensations.deductions',
                    ]);
                },
            ])
            ->whereBetween('work_date', [$monthStart, $monthEnd])
            ->when($selectedEmployeeId, fn ($query) => $query->where('employee_id', $selectedEmployeeId))
            ->selectRaw('employee_id, work_date, COUNT(*) as sessions_count, SUM(active_seconds) as active_seconds, MIN(started_at) as first_started_at, MAX(COALESCE(ended_at, last_activity_at)) as last_activity_at')
            ->groupBy('employee_id', 'work_date')
            ->orderByDesc('work_date')
            ->paginate(30)
            ->withQueryString();

        $dailyLogs->setCollection(
            $dailyLogs->getCollection()->map(function ($row) use ($workSummaryService) {
                $employee = $row->employee;
                $activeSeconds = (int) ($row->active_seconds ?? 0);
                $workDate = Carbon::parse((string) $row->work_date);

                $requiredSeconds = 0;
                $coveragePercent = 0;
                $estimatedAmount = 0.0;

                if ($employee && $workSummaryService->isEligible($employee)) {
                    $requiredSeconds = $workSummaryService->requiredSeconds($employee);
                    $coveragePercent = $requiredSeconds > 0
                        ? (int) round(min(100, ($activeSeconds / $requiredSeconds) * 100))
                        : 0;
                    $estimatedAmount = $workSummaryService->calculateAmount($employee, $workDate, $activeSeconds);
                }

                $row->work_date = $workDate;
                $row->active_seconds = $activeSeconds;
                $row->required_seconds = $requiredSeconds;
                $row->coverage_percent = $coveragePercent;
                $row->estimated_amount = round($estimatedAmount, 2);
                $row->currency = $employee?->activeCompensation?->currency ?? 'BDT';

                return $row;
            })
        );

        if ($selectedEmployeeId) {
            $selectedEmployee = Employee::query()
                ->with('activeCompensation')
                ->find($selectedEmployeeId);

            if ($selectedEmployee
                && $selectedEmployee->employment_type === 'part_time'
                && $workSummaryService->isEligible($selectedEmployee)) {
                $holidayDates = PaidHoliday::query()
                    ->where('is_paid', true)
                    ->whereBetween('holiday_date', [$monthStart, $monthEnd])
                    ->pluck('holiday_date')
                    ->map(fn ($date) => Carbon::parse((string) $date)->toDateString());

                $rows = $dailyLogs->getCollection();
                $existingDates = $rows
                    ->where('employee_id', $selectedEmployeeId)
                    ->map(fn ($row) => Carbon::parse((string) $row->work_date)->toDateString())
                    ->flip();

                $holidayRows = collect();
                foreach ($holidayDates as $holidayDate) {
                    if (isset($existingDates[$holidayDate])) {
                        continue;
                    }

                    $workDate = Carbon::parse($holidayDate);
                    $requiredSeconds = $workSummaryService->requiredSeconds($selectedEmployee);
                    $synthetic = new \stdClass;
                    $synthetic->employee_id = $selectedEmployee->id;
                    $synthetic->employee = $selectedEmployee;
                    $synthetic->work_date = $workDate;
                    $synthetic->sessions_count = 0;
                    $synthetic->first_started_at = null;
                    $synthetic->last_activity_at = null;
                    $synthetic->active_seconds = 0;
                    $synthetic->required_seconds = $requiredSeconds;
                    $synthetic->coverage_percent = 100;
                    $synthetic->estimated_amount = round(
                        $workSummaryService->calculateAmount($selectedEmployee, $workDate, 0),
                        2
                    );
                    $synthetic->currency = $selectedEmployee->activeCompensation?->currency ?? 'BDT';

                    $holidayRows->push($synthetic);
                }

                if ($holidayRows->isNotEmpty()) {
                    $merged = $rows
                        ->concat($holidayRows)
                        ->sortByDesc(fn ($row) => Carbon::parse((string) $row->work_date)->toDateString())
                        ->values();

                    $dailyLogs->setCollection($merged);
                }
            }
        }

        $employees = Employee::query()
            ->whereHas('workSessions')
            ->orderBy('name')
            ->get(['id', 'name']);

        $formatDuration = function (int $seconds): string {
            $hours = (int) floor($seconds / 3600);
            $minutes = (int) floor(($seconds % 3600) / 60);
            $secs = (int) ($seconds % 60);

            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        };

        return Inertia::render('Admin/Hr/WorkLogs/Index', [
            'pageTitle' => 'Work Logs',
            'selectedMonth' => $selectedMonth,
            'selectedEmployeeId' => $selectedEmployeeId,
            'employees' => $employees->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
            ])->values(),
            'dailyLogs' => $dailyLogs->getCollection()->map(function ($log) use ($formatDuration) {
                $workDate = $log->work_date instanceof Carbon
                    ? $log->work_date->format(config('app.date_format', 'd-m-Y'))
                    : Carbon::parse((string) $log->work_date)->format(config('app.date_format', 'd-m-Y'));

                return [
                    'employee_name' => $log->employee?->name ?? '--',
                    'work_date' => $workDate,
                    'sessions_count' => (int) ($log->sessions_count ?? 0),
                    'first_started_at' => $log->first_started_at ? Carbon::parse((string) $log->first_started_at)->format(config('app.datetime_format', 'd-m-Y h:i A')) : '--',
                    'last_activity_at' => $log->last_activity_at ? Carbon::parse((string) $log->last_activity_at)->format(config('app.datetime_format', 'd-m-Y h:i A')) : '--',
                    'active_duration' => $formatDuration((int) ($log->active_seconds ?? 0)),
                    'required_duration' => $formatDuration((int) ($log->required_seconds ?? 0)),
                    'coverage_percent' => (int) ($log->coverage_percent ?? 0),
                    'currency' => $log->currency ?? 'BDT',
                    'estimated_amount' => number_format((float) ($log->estimated_amount ?? 0), 2),
                ];
            })->values(),
            'pagination' => [
                'previous_url' => $dailyLogs->previousPageUrl(),
                'next_url' => $dailyLogs->nextPageUrl(),
                'has_pages' => $dailyLogs->hasPages(),
            ],
            'routes' => [
                'index' => route('admin.hr.timesheets.index'),
            ],
        ]);
    }
}
