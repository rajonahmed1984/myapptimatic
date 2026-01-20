<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeCompensation;
use Carbon\Carbon;

class EmployeeWorkSummaryService
{
    public function isEligible(Employee $employee): bool
    {
        return in_array($employee->employment_type, ['full_time', 'part_time'], true)
            && $employee->work_mode === 'remote';
    }

    public function requiredSeconds(Employee $employee): int
    {
        return $employee->employment_type === 'part_time' ? 4 * 3600 : 8 * 3600;
    }

    public function calculateAmount(Employee $employee, Carbon $date, int $activeSeconds): float
    {
        $compensation = $employee->activeCompensation;
        if (! $compensation instanceof EmployeeCompensation) {
            return 0.0;
        }

        $requiredSeconds = $this->requiredSeconds($employee);
        if ($requiredSeconds <= 0 || $activeSeconds <= 0) {
            return 0.0;
        }

        $requiredHours = $requiredSeconds / 3600;
        $activeHours = min($activeSeconds, $requiredSeconds) / 3600;

        if (($compensation->salary_type ?? 'monthly') === 'hourly') {
            $rate = (float) ($compensation->overtime_rate ?? $compensation->basic_pay ?? 0);
            return $this->roundMoney($activeHours * $rate);
        }

        $monthlyGross = (float) ($compensation->basic_pay ?? 0);
        $monthlyGross += $this->sumArray($compensation->allowances);
        $monthlyGross -= $this->sumArray($compensation->deductions);

        $daysInMonth = max(1, $date->daysInMonth);
        $dailyRate = $monthlyGross / $daysInMonth;
        $ratio = $requiredHours > 0 ? ($activeHours / $requiredHours) : 0;

        return $this->roundMoney($dailyRate * $ratio);
    }

    private function sumArray($value): float
    {
        if (! is_array($value)) {
            return 0.0;
        }

        return (float) array_reduce($value, function ($carry, $item) {
            return $carry + (float) ($item['amount'] ?? $item ?? 0);
        }, 0.0);
    }

    private function roundMoney(float $amount): float
    {
        return round($amount, 2, PHP_ROUND_HALF_UP);
    }
}
