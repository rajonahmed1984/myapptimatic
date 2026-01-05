<?php

namespace App\Providers;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollItem;
use App\Models\Timesheet;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Policies\EmployeePolicy;
use App\Policies\LeaveRequestPolicy;
use App\Policies\PayrollItemPolicy;
use App\Policies\TimesheetPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\ProjectTaskPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Employee::class => EmployeePolicy::class,
        Timesheet::class => TimesheetPolicy::class,
        LeaveRequest::class => LeaveRequestPolicy::class,
        PayrollItem::class => PayrollItemPolicy::class,
        Project::class => ProjectPolicy::class,
        ProjectTask::class => ProjectTaskPolicy::class,
    ];

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
