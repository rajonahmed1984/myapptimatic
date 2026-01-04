<?php

namespace App\Policies;

use App\Models\PayrollItem;

class PayrollItemPolicy extends BasePolicy
{
    public function viewAny($user): bool
    {
        return $this->isAdmin($user) || $this->employeeId($user) !== null;
    }

    public function view($user, PayrollItem $payrollItem): bool
    {
        return $this->isAdmin($user) || $this->employeeId($user) === $payrollItem->employee_id;
    }

    public function update($user, PayrollItem $payrollItem): bool
    {
        return $this->isAdmin($user);
    }

    public function markPaid($user, PayrollItem $payrollItem): bool
    {
        return $this->isAdmin($user);
    }
}
