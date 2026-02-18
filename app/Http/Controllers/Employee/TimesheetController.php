<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\EmployeeWorkSession;
use App\Services\EmployeeWorkSummaryService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimesheetController extends Controller
{
    public function index(Request $request, EmployeeWorkSummaryService $workSummaryService): View|RedirectResponse
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

        return view('employee.work-logs.index', [
            'dailyLogs' => $dailyLogs,
            'isEligible' => $isEligible,
            'requiredSeconds' => $requiredSeconds,
            'selectedMonth' => $selectedMonth,
        ]);
    }
}
