<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\EmployeeWorkSession;
use App\Models\EmployeeWorkSummary;
use App\Services\EmployeeWorkSummaryService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateEmployeeWorkSummaries extends Command
{
    protected $signature = 'employee-work-summaries:generate {--date=}';
    protected $description = 'Generate daily work summaries for remote part-time and full-time employees.';

    public function handle(EmployeeWorkSummaryService $summaryService): int
    {
        $dateInput = $this->option('date');
        $date = $dateInput ? Carbon::parse($dateInput)->startOfDay() : now()->subDay()->startOfDay();
        $workDate = $date->toDateString();

        $employees = Employee::query()
            ->where('status', 'active')
            ->whereIn('employment_type', ['full_time', 'part_time'])
            ->where('work_mode', 'remote')
            ->with('activeCompensation')
            ->get();

        $created = 0;

        foreach ($employees as $employee) {
            $activeSeconds = (int) EmployeeWorkSession::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $workDate)
                ->sum('active_seconds');

            $requiredSeconds = $summaryService->requiredSeconds($employee);
            $amount = $summaryService->calculateAmount($employee, $date, $activeSeconds);

            $summary = EmployeeWorkSummary::firstOrCreate(
                [
                    'employee_id' => $employee->id,
                    'work_date' => $workDate,
                ],
                [
                    'active_seconds' => $activeSeconds,
                    'required_seconds' => $requiredSeconds,
                    'generated_salary_amount' => $amount,
                    'status' => 'generated',
                ]
            );

            if ($summary->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->info("Employee work summaries generated for {$workDate}: {$created} created.");

        return self::SUCCESS;
    }
}
