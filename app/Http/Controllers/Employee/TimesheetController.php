<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\EmployeeWorkSession;
use App\Services\EmployeeWorkSummaryService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TimesheetController extends Controller
{
    public function index(Request $request, EmployeeWorkSummaryService $workSummaryService): View|InertiaResponse|RedirectResponse
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $selectedMonth = $filters['month'] ?? now()->format('Y-m');
        [$year, $month] = explode('-', $selectedMonth);
        $monthStart = Carbon::createFromDate((int) $year, (int) $month, 1)->startOfMonth()->toDateString();
        $monthEnd = Carbon::createFromDate((int) $year, (int) $month, 1)->endOfMonth()->toDateString();

        $employee = $request->attributes->get('employee');
        $employee->loadMissing('activeCompensation');
        $isEligible = $workSummaryService->isEligible($employee);

        if (! $isEligible) {
            return redirect()
                ->route('employee.dashboard')
                ->with('status', 'Work logs are available only for remote full-time or part-time employees.');
        }

        $dailyLogs = EmployeeWorkSession::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$monthStart, $monthEnd])
            ->selectRaw('work_date, COUNT(*) as sessions_count, SUM(active_seconds) as active_seconds, MIN(started_at) as first_started_at, MAX(COALESCE(ended_at, last_activity_at)) as last_activity_at')
            ->groupBy('work_date')
            ->orderByDesc('work_date')
            ->paginate(15)
            ->withQueryString();

        $requiredSeconds = $isEligible ? $workSummaryService->requiredSeconds($employee) : 0;
        $currency = $employee->activeCompensation?->currency ?? 'BDT';

        $dailyLogs->setCollection(
            $dailyLogs->getCollection()->map(function ($row) use ($employee, $workSummaryService, $requiredSeconds, $currency) {
                $workDate = Carbon::parse((string) $row->work_date);
                $activeSeconds = (int) ($row->active_seconds ?? 0);
                $estimatedAmount = $requiredSeconds > 0
                    ? $workSummaryService->calculateAmount($employee, $workDate, $activeSeconds)
                    : 0.0;

                $coveragePercent = $requiredSeconds > 0
                    ? (int) round(min(100, ($activeSeconds / $requiredSeconds) * 100))
                    : 0;

                $row->work_date = $workDate;
                $row->active_seconds = $activeSeconds;
                $row->required_seconds = $requiredSeconds;
                $row->coverage_percent = $coveragePercent;
                $row->estimated_amount = $estimatedAmount;
                $row->currency = $currency;

                return $row;
            })
        );

        return Inertia::render('Employee/Timesheets/Index', [
            'daily_logs' => $dailyLogs->getCollection()->map(function ($log) {
                $seconds = (int) ($log->active_seconds ?? 0);
                $hours = (int) floor($seconds / 3600);
                $minutes = (int) floor(($seconds % 3600) / 60);
                $secs = (int) ($seconds % 60);

                $required = (int) ($log->required_seconds ?? 0);
                $rHours = (int) floor($required / 3600);
                $rMinutes = (int) floor(($required % 3600) / 60);
                $rSeconds = (int) ($required % 60);

                return [
                    'work_date_display' => $log->work_date?->format(config('app.date_format', 'Y-m-d')) ?? '--',
                    'sessions_count' => (int) ($log->sessions_count ?? 0),
                    'first_started_at' => $log->first_started_at ? Carbon::parse($log->first_started_at)->format('H:i:s') : '--',
                    'last_activity_at' => $log->last_activity_at ? Carbon::parse($log->last_activity_at)->format('H:i:s') : '--',
                    'active_time_label' => sprintf('%02d:%02d:%02d', $hours, $minutes, $secs),
                    'required_time_label' => sprintf('%02d:%02d:%02d', $rHours, $rMinutes, $rSeconds),
                    'coverage_percent' => (int) ($log->coverage_percent ?? 0),
                    'estimated_amount' => (float) ($log->estimated_amount ?? 0),
                    'currency' => $log->currency ?? 'BDT',
                ];
            })->values()->all(),
            'eligible' => $isEligible,
            'required_seconds' => $requiredSeconds,
            'selected_month' => $selectedMonth,
            'subtotal_estimated' => (float) $dailyLogs->getCollection()->sum(fn ($log) => (float) ($log->estimated_amount ?? 0)),
            'subtotal_currency' => $dailyLogs->getCollection()->first()?->currency ?? 'BDT',
            'pagination' => [
                'current_page' => $dailyLogs->currentPage(),
                'last_page' => $dailyLogs->lastPage(),
                'per_page' => $dailyLogs->perPage(),
                'total' => $dailyLogs->total(),
                'from' => $dailyLogs->firstItem(),
                'to' => $dailyLogs->lastItem(),
                'prev_page_url' => $dailyLogs->previousPageUrl(),
                'next_page_url' => $dailyLogs->nextPageUrl(),
            ],
            'routes' => [
                'index' => route('employee.timesheets.index'),
                'dashboard' => route('employee.dashboard'),
            ],
        ]);
    }
}
