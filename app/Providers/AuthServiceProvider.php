<?php

namespace App\Providers;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollItem;
use App\Models\Timesheet;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskSubtask;
use App\Models\License;
use App\Policies\EmployeePolicy;
use App\Policies\LeaveRequestPolicy;
use App\Policies\PayrollItemPolicy;
use App\Policies\TimesheetPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\ProjectTaskPolicy;
use App\Policies\ProjectTaskSubtaskPolicy;
use App\Policies\LicensePolicy;
use App\Policies\DocumentPolicy;
use App\Enums\Role;
use Illuminate\Auth\Notifications\ResetPassword;
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
        ProjectTaskSubtask::class => ProjectTaskSubtaskPolicy::class,
        License::class => LicensePolicy::class,
    ];

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        Gate::define('view-documents', [DocumentPolicy::class, 'view']);

        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            $route = match ($notifiable->role ?? null) {
                Role::EMPLOYEE => 'employee.password.reset',
                Role::SALES => 'sales.password.reset',
                Role::SUPPORT => 'support.password.reset',
                default => 'password.reset',
            };

            return url(route($route, [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));
        });
    }
}
