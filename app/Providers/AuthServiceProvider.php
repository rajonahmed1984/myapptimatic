<?php

namespace App\Providers;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollItem;
use App\Models\Timesheet;
use App\Policies\EmployeePolicy;
use App\Policies\LeaveRequestPolicy;
use App\Policies\PayrollItemPolicy;
use App\Policies\TimesheetPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Employee::class => EmployeePolicy::class,
        Timesheet::class => TimesheetPolicy::class,
        LeaveRequest::class => LeaveRequestPolicy::class,
        PayrollItem::class => PayrollItemPolicy::class,
    ];

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
