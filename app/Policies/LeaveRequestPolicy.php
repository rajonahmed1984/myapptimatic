<?php

namespace App\Policies;

use App\Models\LeaveRequest;

class LeaveRequestPolicy extends BasePolicy
{
    public function viewAny($user): bool
    {
        return $this->isAdmin($user) || $this->employeeId($user) !== null;
    }

    public function view($user, LeaveRequest $leaveRequest): bool
    {
        return $this->isAdmin($user) || $this->employeeId($user) === $leaveRequest->employee_id;
    }

    public function create($user): bool
    {
        return $this->isAdmin($user) || $this->employeeId($user) !== null;
    }

    public function approve($user, LeaveRequest $leaveRequest): bool
    {
        return $this->isAdmin($user);
    }
}
