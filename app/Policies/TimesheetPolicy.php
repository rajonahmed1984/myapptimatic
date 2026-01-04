<?php

namespace App\Policies;

use App\Models\Timesheet;

class TimesheetPolicy extends BasePolicy
{
    public function viewAny($user): bool
    {
        return $this->isAdmin($user) || $this->employeeId($user) !== null;
    }

    public function view($user, Timesheet $timesheet): bool
    {
        return $this->isAdmin($user) || $this->employeeId($user) === $timesheet->employee_id;
    }

    public function update($user, Timesheet $timesheet): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $this->employeeId($user) === $timesheet->employee_id && in_array($timesheet->status, ['draft', 'submitted'], true);
    }

    public function approve($user, Timesheet $timesheet): bool
    {
        return $this->isAdmin($user);
    }
}
