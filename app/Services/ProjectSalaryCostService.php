<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ProjectSalaryCostService
{
    /**
     * Monthly salary cost of a project's assigned employees across the project's dates.
     * On days an employee is on several running projects, that day's pay is split equally.
     * Contract employees are skipped because their pay is already in contract_amount.
     *
     * @return array{total: float, not_counted: array<int, string>, employees: array<int, array<string, mixed>>}
     */
    public function forProject(Project $project): array
    {
        $employees = $project->employees()
            ->with(['compensations', 'projects:id,status,start_date,expected_end_date,due_date,created_at,updated_at'])
            ->get();

        $total = 0.0;
        $notCounted = [];
        $rows = [];

        foreach ($employees as $employee) {
            $isContract = $employee->employment_type === 'contract';
            $compensations = $isContract ? collect() : $this->monthlyCompensations($employee, (string) $project->currency);
            $usage = $this->employeeUsage($employee, $compensations, $project->id);

            if (! $isContract && $compensations->isEmpty()) {
                $notCounted[] = (string) $employee->name;
            }

            $salary = $compensations->isEmpty() ? null : round($usage['cost'], 2, PHP_ROUND_HALF_UP);
            $total += (float) $salary;
            $rows[] = [
                'id' => $employee->id,
                'name' => (string) $employee->name,
                'is_contract' => $isContract,
                'days' => $usage['days'],
                'shared_days' => $usage['shared_days'],
                'salary' => $salary,
            ];
        }

        return [
            'total' => round($total, 2, PHP_ROUND_HALF_UP),
            'not_counted' => $notCounted,
            'employees' => $rows,
        ];
    }

    /**
     * Calendar days from the project start to its end, counting both ends.
     */
    public function durationDays(Project $project): int
    {
        [$start, $end] = $this->projectWindow($project);

        return $this->daysBetween($start, $end);
    }

    private function daysBetween(string $start, string $end): int
    {
        return $start > $end ? 0 : (int) CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end)) + 1;
    }

    private function monthlyCompensations(Employee $employee, string $currency): Collection
    {
        return $employee->compensations
            ->filter(fn (EmployeeCompensation $compensation) => ($compensation->is_active || $compensation->effective_to)
                && $compensation->salary_type === 'monthly'
                && strcasecmp((string) $compensation->currency, $currency) === 0)
            ->sortByDesc(fn (EmployeeCompensation $compensation) => $compensation->effective_from?->toDateString())
            ->values();
    }

    /**
     * @return array{cost: float, days: int, shared_days: int}
     */
    private function employeeUsage(Employee $employee, Collection $compensations, int $projectId): array
    {
        $windows = $employee->projects->mapWithKeys(fn (Project $assigned) => [$assigned->id => $this->projectWindow($assigned)]);
        $window = $windows->get($projectId);
        if (! $window) {
            return ['cost' => 0.0, 'days' => 0, 'shared_days' => 0];
        }

        [$start, $end] = $window;
        if ($employee->join_date && $employee->join_date->toDateString() > $start) {
            $start = $employee->join_date->toDateString();
        }
        if ($employee->exit_date && $employee->exit_date->toDateString() < $end) {
            $end = $employee->exit_date->toDateString();
        }

        $cost = 0.0;
        $sharedDays = 0;
        for ($day = CarbonImmutable::parse($start); $day->toDateString() <= $end; $day = $day->addDay()) {
            $date = $day->toDateString();
            $projectsOnDay = $windows->filter(fn (array $range) => $range[0] <= $date && $range[1] >= $date)->count();
            if ($projectsOnDay > 1) {
                $sharedDays++;
            }

            $compensation = $compensations->first(fn (EmployeeCompensation $item) => $item->effective_from?->toDateString() <= $date
                && (! $item->effective_to || $item->effective_to->toDateString() >= $date));
            if (! $compensation) {
                continue;
            }

            $cost += $this->monthlyPay($compensation) / $day->daysInMonth / max(1, $projectsOnDay);
        }

        return ['cost' => $cost, 'days' => $this->daysBetween($start, $end), 'shared_days' => $sharedDays];
    }

    /**
     * Running projects count up to today. Closed projects stop at their planned end date,
     * or the last update when that came first, because no completion date is stored.
     *
     * @return array{0: string, 1: string}
     */
    private function projectWindow(Project $project): array
    {
        $start = ($project->start_date ?? $project->created_at ?? now())->toDateString();
        $end = now()->toDateString();

        if (in_array($project->status, ['complete', 'cancel'], true)) {
            $closedAt = ($project->updated_at ?? now())->toDateString();
            $plannedEnd = ($project->expected_end_date ?? $project->due_date)?->toDateString();
            $end = $plannedEnd && $plannedEnd < $closedAt ? $plannedEnd : $closedAt;
        }

        return [$start, $end];
    }

    private function monthlyPay(EmployeeCompensation $compensation): float
    {
        return max(0.0, (float) $compensation->basic_pay
            + $this->sumAmounts($compensation->allowances)
            - $this->sumAmounts($compensation->deductions));
    }

    private function sumAmounts($items): float
    {
        return (float) collect(is_array($items) ? $items : [])
            ->sum(fn ($item) => (float) (is_array($item) ? ($item['amount'] ?? 0) : $item));
    }
}
