<?php

namespace App\Policies;

use App\Models\Employee;

class EmployeePolicy extends BasePolicy
{
    public function viewAny($user): bool
    {
        return $this->isAdmin($user);
    }

    public function view($user, Employee $employee): bool
    {
        return $this->isAdmin($user) || $this->employeeId($user) === $employee->id;
    }

    public function create($user): bool
    {
        return $this->isAdmin($user);
    }

    public function update($user, Employee $employee): bool
    {
        return $this->isAdmin($user);
    }

    public function delete($user, Employee $employee): bool
    {
        return $this->isAdmin($user);
    }
}
