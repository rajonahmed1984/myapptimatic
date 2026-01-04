<?php

namespace App\Policies;

use App\Models\Employee;

abstract class BasePolicy
{
    protected function isAdmin($user): bool
    {
        return method_exists($user, 'isAdmin') && $user->isAdmin();
    }

    protected function employeeId($user): ?int
    {
        if ($user instanceof Employee) {
            return $user->id;
        }

        if (method_exists($user, 'employee')) {
            $employee = $user->employee;
            return $employee?->id;
        }

        return null;
    }
}
