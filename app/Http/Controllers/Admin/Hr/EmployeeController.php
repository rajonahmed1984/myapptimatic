<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeUserRequest;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCompensation;
use App\Models\EmployeePayout;
use App\Models\EmployeeSession;
use App\Models\EmployeeWorkSession;
use App\Models\EmployeeWorkSummary;
use App\Models\PaidHoliday;
use App\Models\PaymentMethod;
use App\Models\PayrollItem;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskSubtask;
use App\Models\User;
use App\Services\EmployeeWorkSummaryService;
use App\Support\Currency;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class EmployeeController extends Controller
{
    public function index(): InertiaResponse
    {
        $employees = Employee::query()
            ->with(['manager', 'user:id,avatar_path'])
            ->orderByDesc('id')
            ->paginate(20);

        $loginStatuses = $this->resolveEmployeeLoginStatuses($employees);
        $lastLoginByEmployee = $this->resolveEmployeeLastLogins($employees);

        return Inertia::render('Admin/Hr/Employees/Index', [
            'pageTitle' => 'Employees',
            'employees' => $employees->through(function (Employee $employee) use ($loginStatuses, $lastLoginByEmployee) {
                $loginStatus = $loginStatuses[$employee->id] ?? 'logout';
                $showLoginBadge = $loginStatus === 'login';
                $loginLabel = 'Login';
                $loginClasses = 'border-emerald-200 text-emerald-700 bg-emerald-50';
                $lastLoginAt = $lastLoginByEmployee[$employee->id] ?? null;
                $photoPath = $employee->photo_path ?: $employee->user?->avatar_path;

                return [
                    'id' => $employee->id,
                    'photo_url' => $this->publicFileUrl($photoPath),
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'designation' => $employee->designation ?? '--',
                    'employment_type' => ucfirst((string) ($employee->employment_type ?? '--')),
                    'join_date' => $employee->join_date?->format(config('app.date_format', 'd-m-Y')) ?? '--',
                    'manager_name' => $employee->manager?->name ?? '--',
                    'status' => (string) $employee->status,
                    'status_label' => ucfirst((string) $employee->status),
                    'show_login_badge' => $showLoginBadge,
                    'login_label' => $loginLabel,
                    'login_classes' => $loginClasses,
                    'last_login_at' => $lastLoginAt ? Carbon::parse((string) $lastLoginAt)->format(config('app.datetime_format', 'd-m-Y h:i A')) : '--',
                    'routes' => [
                        'show' => route('admin.hr.employees.show', $employee),
                        'edit' => route('admin.hr.employees.edit', $employee),
                        'destroy' => route('admin.hr.employees.destroy', $employee),
                    ],
                ];
            })->values(),
            'pagination' => [
                'previous_url' => $employees->previousPageUrl(),
                'next_url' => $employees->nextPageUrl(),
                'has_pages' => $employees->hasPages(),
            ],
            'routes' => [
                'create' => route('admin.hr.employees.create'),
            ],
        ]);
    }

    private function publicFileUrl(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $trimmedPath = trim($path);
        if (Str::startsWith($trimmedPath, ['http://', 'https://'])) {
            return $trimmedPath;
        }

        $normalizedPath = ltrim($trimmedPath, '/');
        if (Str::startsWith($normalizedPath, 'storage/')) {
            return asset($normalizedPath);
        }

        return Storage::disk('public')->url($normalizedPath);
    }

    public function create(): InertiaResponse
    {
        $managers = Employee::query()->orderBy('name')->get();
        $users = User::query()->orderBy('name')->get();

        return Inertia::render('Admin/Hr/Employees/Create', [
            'pageTitle' => 'Add Employee',
            'managers' => $managers->map(fn (Employee $manager) => [
                'id' => $manager->id,
                'name' => $manager->name,
            ])->values(),
            'users' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])->values(),
            'currencyOptions' => ['BDT', 'USD'],
            'defaults' => [
                'join_date' => now()->toDateString(),
                'employment_type' => 'full_time',
                'work_mode' => 'remote',
                'status' => 'active',
                'salary_type' => 'monthly',
                'currency' => 'BDT',
                'basic_pay' => 0,
            ],
            'routes' => [
                'index' => route('admin.hr.employees.index'),
                'store' => route('admin.hr.employees.store'),
            ],
        ]);
    }

    public function store(StoreEmployeeUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['currency'] = strtoupper($data['currency']);
        $data['basic_pay'] = $data['basic_pay'] ?? 0;

        $userId = $data['user_id'] ?? null;
        $passwordInput = $data['user_password'] ?? null;
        $linkedUser = null;

        if (! $userId && $passwordInput) {
            $existingUser = User::query()->where('email', $data['email'])->first();

            if ($existingUser) {
                return back()
                    ->withErrors(['email' => 'This email already belongs to another user. Link that user instead.'])
                    ->withInput();
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($passwordInput),
                'role' => Role::EMPLOYEE,
            ]);

            $userId = $user->id;
            $linkedUser = $user;
        } elseif ($userId) {
            $linkedUser = User::query()->find($userId);
        }

        $employeeData = collect($data)->only([
            'user_id',
            'manager_id',
            'name',
            'email',
            'phone',
            'address',
            'designation',
            'department',
            'employment_type',
            'work_mode',
            'join_date',
            'status',
        ])->toArray();

        $employeeData['user_id'] = $userId;

        $employee = Employee::create($employeeData);

        if (! empty($userId)) {
            $updates = ['role' => Role::EMPLOYEE];
            if ($passwordInput) {
                $updates['password'] = Hash::make($passwordInput);
            }
            User::whereKey($userId)->update($updates);
        }

        $uploadPaths = $this->handleUploads($request);
        if (! empty($uploadPaths)) {
            $employee->update($uploadPaths);
        } elseif (! $request->hasFile('photo') && $linkedUser?->avatar_path) {
            $employee->update(['photo_path' => $linkedUser->avatar_path]);
        }

        EmployeeCompensation::create([
            'employee_id' => $employee->id,
            'salary_type' => $data['salary_type'],
            'currency' => $data['currency'],
            'basic_pay' => $data['basic_pay'],
            'overtime_rate' => $data['hourly_rate'] ?? null,
            'effective_from' => $data['join_date'],
            'is_active' => true,
            'set_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.hr.employees.index')
            ->with('status', 'Employee created.');
    }

    public function edit(Employee $employee): InertiaResponse
    {
        $employee->load('activeCompensation');
        $managers = Employee::query()->where('id', '!=', $employee->id)->orderBy('name')->get();
        $users = User::query()->orderBy('name')->get();

        return Inertia::render('Admin/Hr/Employees/Edit', [
            'pageTitle' => 'Edit Employee',
            'employee' => [
                'id' => $employee->id,
                'user_id' => $employee->user_id,
                'manager_id' => $employee->manager_id,
                'name' => $employee->name,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'address' => $employee->address,
                'department' => $employee->department,
                'designation' => $employee->designation,
                'join_date' => $employee->join_date?->format(config('app.date_format', 'd-m-Y')),
                'employment_type' => $employee->employment_type,
                'work_mode' => $employee->work_mode,
                'status' => $employee->status,
                'salary_type' => $employee->activeCompensation?->salary_type,
                'currency' => $employee->activeCompensation?->currency,
                'basic_pay' => $employee->activeCompensation?->basic_pay ?? 0,
                'hourly_rate' => $employee->activeCompensation?->overtime_rate,
                'photo_path' => $employee->photo_path,
                'nid_path' => $employee->nid_path,
                'cv_path' => $employee->cv_path,
            ],
            'managers' => $managers->map(fn (Employee $manager) => [
                'id' => $manager->id,
                'name' => $manager->name,
            ])->values(),
            'users' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])->values(),
            'currencyOptions' => ['BDT', 'USD'],
            'documentLinks' => [
                'nid' => $employee->nid_path ? route('admin.user-documents.show', ['type' => 'employee', 'id' => $employee->id, 'doc' => 'nid']) : null,
                'cv' => $employee->cv_path ? route('admin.user-documents.show', ['type' => 'employee', 'id' => $employee->id, 'doc' => 'cv']) : null,
            ],
            'routes' => [
                'index' => route('admin.hr.employees.index'),
                'update' => route('admin.hr.employees.update', $employee),
            ],
        ]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $originalUserId = $employee->user_id;
        $data = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:employees,email,'.$employee->id],
            'phone' => ['nullable', 'string', 'max:50'],
            'designation' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'employment_type' => ['required', 'in:full_time,part_time,contract'],
            'work_mode' => ['required', 'in:remote,on_site,hybrid'],
            'join_date' => ['required', 'date'],
            'status' => ['required', 'in:active,inactive'],
            'salary_type' => ['required', 'in:monthly,hourly,project_base'],
            'currency' => ['required', 'string', 'size:3', Rule::in(Currency::allowed())],
            'basic_pay' => [
                Rule::requiredIf(fn () => ! ($request->input('employment_type') === 'contract' && $request->input('salary_type') === 'project_base')),
                'nullable',
                'numeric',
            ],
            'hourly_rate' => ['nullable', 'numeric'],
            'nid_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:4096'],
            'cv_file' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'user_password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $data['currency'] = strtoupper($data['currency']);
        $data['basic_pay'] = $data['basic_pay'] ?? 0;
        $employee->update($data);

        $userId = $data['user_id'] ?? $employee->user_id;
        $passwordInput = $data['user_password'] ?? null;
        $linkedUser = null;

        if ($userId) {
            $user = User::query()->find($userId);
            if ($user) {
                $updates = [
                    'role' => Role::EMPLOYEE,
                ];

                if ($user->email !== $employee->email) {
                    $emailExists = User::query()
                        ->where('email', $employee->email)
                        ->where('id', '!=', $user->id)
                        ->exists();

                    if ($emailExists) {
                        return back()
                            ->withErrors(['email' => 'This email already belongs to another user.'])
                            ->withInput();
                    }

                    $updates['email'] = $employee->email;
                }

                if ($user->name !== $employee->name) {
                    $updates['name'] = $employee->name;
                }

                if ($passwordInput) {
                    $updates['password'] = Hash::make($passwordInput);
                }

                $user->update($updates);
                $linkedUser = $user;
            }
        } elseif ($passwordInput) {
            $existingUser = User::query()->where('email', $employee->email)->first();

            if ($existingUser) {
                return back()
                    ->withErrors(['email' => 'This email already belongs to another user.'])
                    ->withInput();
            }

            $user = User::create([
                'name' => $employee->name,
                'email' => $employee->email,
                'password' => Hash::make($passwordInput),
                'role' => Role::EMPLOYEE,
            ]);

            $employee->update(['user_id' => $user->id]);
            $linkedUser = $user;
        }

        $uploadPaths = $this->handleUploads($request);
        if (! empty($uploadPaths)) {
            $employee->update($uploadPaths);
        } elseif (! $request->hasFile('photo')
            && $linkedUser
            && $linkedUser->avatar_path
            && $originalUserId !== $linkedUser->id) {
            $employee->update(['photo_path' => $linkedUser->avatar_path]);
        }

        $compensationPayload = [
            'salary_type' => $data['salary_type'],
            'currency' => $data['currency'],
            'basic_pay' => $data['basic_pay'],
            'overtime_rate' => $data['hourly_rate'] ?? null,
            'set_by' => $request->user()?->id,
        ];

        $activeCompensation = $employee->activeCompensation;
        if ($activeCompensation) {
            $activeCompensation->update($compensationPayload);
        } else {
            EmployeeCompensation::create(array_merge($compensationPayload, [
                'employee_id' => $employee->id,
                'effective_from' => $employee->join_date,
                'is_active' => true,
            ]));
        }

        return redirect()->route('admin.hr.employees.index')
            ->with('status', 'Employee updated.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $employee->delete();

        return redirect()->route('admin.hr.employees.index')
            ->with('status', 'Employee removed.');
    }

    public function show(Employee $employee, EmployeeWorkSummaryService $workSummaryService): InertiaResponse
    {
        $employee->load(['manager:id,name', 'user:id,name,email,avatar_path', 'activeCompensation']);

        $comp = $employee->activeCompensation;
        $summary = [
            'salary_type' => $comp?->salary_type,
            'basic_pay' => $comp?->basic_pay,
            'currency' => $comp?->currency,
        ];

        $tab = request()->query('tab', 'profile');
        if ($tab === 'profile' || $tab === 'compensation') {
            $tab = 'profile';
        }
        $allowedTabs = ['profile', 'timesheets', 'leave', 'payroll', 'projects'];
        $isProjectBase = ($summary['salary_type'] ?? null) === 'project_base';
        if ($isProjectBase) {
            $allowedTabs[] = 'earnings';
            $allowedTabs[] = 'payouts';
        }
        if (! in_array($tab, $allowedTabs, true)) {
            $tab = 'profile';
        }

        $projects = collect();
        $projectStatusCounts = collect();
        $projectTaskStatusCounts = collect();
        $taskSummary = null;
        $subtaskSummary = null;
        $taskProgress = null;
        $projectBaseEarnings = null;
        $recentEarnings = collect();
        $recentPayouts = collect();
        $contractProjectsQuery = null;
        $advanceProjects = collect();
        $recentWorkSessions = collect();
        $recentWorkSummaries = collect();
        $recentPayrollItems = collect();
        $recentSalaryAdvances = collect();
        $recentAdvanceTransactions = collect();
        $draftPayrollItem = null;
        $workSessionStats = [
            'eligible' => false,
            'today_active_seconds' => 0,
            'month_active_seconds' => 0,
            'month_required_seconds' => 0,
            'coverage_percent' => 0,
            'today_salary_projection' => 0.0,
            'month_salary_projection' => 0.0,
            'currency' => $summary['currency'] ?? 'BDT',
        ];
        $payrollSourceNote = null;

        if ($isProjectBase) {
            $contractProjectsQuery = Project::query()
                ->whereHas('employees', fn ($query) => $query->whereKey($employee->id))
                ->whereNotNull('contract_employee_total_earned');

            $advanceProjects = Project::query()
                ->with('customer:id,name')
                ->whereHas('employees', fn ($query) => $query->whereKey($employee->id))
                ->orderBy('name')
                ->get(['id', 'name', 'customer_id', 'status']);
        }

        if (in_array($tab, ['profile', 'summary'], true)) {
            $taskBaseQuery = ProjectTask::query()
                ->where(function ($query) use ($employee) {
                    $query->where(function ($inner) use ($employee) {
                        $inner->where('assigned_type', 'employee')
                            ->where('assigned_id', $employee->id);
                    })->orWhereHas('assignments', function ($inner) use ($employee) {
                        $inner->where('assignee_type', 'employee')
                            ->where('assignee_id', $employee->id);
                    });
                });

            $taskStatusCounts = (clone $taskBaseQuery)
                ->select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');

            $taskTotal = (int) $taskStatusCounts->sum();
            $completedTasks = (int) (($taskStatusCounts['completed'] ?? 0) + ($taskStatusCounts['done'] ?? 0));
            $taskSummary = [
                'total' => $taskTotal,
                'projects' => (int) (clone $taskBaseQuery)->distinct('project_id')->count('project_id'),
                'pending' => (int) ($taskStatusCounts['pending'] ?? 0),
                'in_progress' => (int) ($taskStatusCounts['in_progress'] ?? 0),
                'blocked' => (int) ($taskStatusCounts['blocked'] ?? 0),
                'completed' => $completedTasks,
                'other' => (int) $taskStatusCounts->except(['pending', 'in_progress', 'blocked', 'completed', 'done'])->sum(),
            ];

            $taskProgress = [
                'percent' => $taskTotal > 0 ? (int) round(($completedTasks / $taskTotal) * 100) : 0,
            ];

            $subtaskCounts = ProjectTaskSubtask::query()
                ->select('is_completed', DB::raw('COUNT(*) as total'))
                ->whereIn('project_task_id', (clone $taskBaseQuery)->select('id'))
                ->groupBy('is_completed')
                ->pluck('total', 'is_completed');

            $subtaskTotal = (int) $subtaskCounts->sum();
            $subtaskCompleted = (int) ($subtaskCounts[1] ?? 0);

            $subtaskSummary = [
                'total' => $subtaskTotal,
                'completed' => $subtaskCompleted,
                'pending' => $subtaskTotal - $subtaskCompleted,
            ];

            if ($contractProjectsQuery) {
                $totalEarned = (float) (clone $contractProjectsQuery)->sum('contract_employee_total_earned');
                $payableRaw = (float) (clone $contractProjectsQuery)->sum('contract_employee_payable');
                $paid = (float) EmployeePayout::query()
                    ->where('employee_id', $employee->id)
                    ->sum('amount');
                $advancePaid = (float) EmployeePayout::query()
                    ->where('employee_id', $employee->id)
                    ->where('metadata->type', 'advance')
                    ->sum('amount');

                $projectBaseEarnings = [
                    'total_earned' => $totalEarned,
                    'payable' => max(0, $payableRaw - $paid),
                    'paid' => $paid,
                    'advance_paid' => $advancePaid,
                ];
            }
        }

        if ($tab === 'earnings' && $contractProjectsQuery) {
            $totalEarned = (float) (clone $contractProjectsQuery)->sum('contract_employee_total_earned');
            $payableRaw = (float) (clone $contractProjectsQuery)->sum('contract_employee_payable');
            $paid = (float) EmployeePayout::query()
                ->where('employee_id', $employee->id)
                ->sum('amount');
            $projectBaseEarnings = [
                'total_earned' => $totalEarned,
                'payable' => max(0, $payableRaw - $paid),
                'paid' => $paid,
            ];

            $recentEarnings = (clone $contractProjectsQuery)
                ->orderByDesc('updated_at')
                ->limit(10)
                ->get([
                    'id',
                    'name',
                    'status',
                    'currency',
                    'contract_employee_total_earned',
                    'contract_employee_payable',
                    'contract_employee_payout_status',
                    'updated_at',
                ]);
        }

        if ($tab === 'payouts' && $contractProjectsQuery) {
            $totalEarned = (float) (clone $contractProjectsQuery)->sum('contract_employee_total_earned');
            $payableRaw = (float) (clone $contractProjectsQuery)->sum('contract_employee_payable');
            $paid = (float) EmployeePayout::query()
                ->where('employee_id', $employee->id)
                ->sum('amount');
            $projectBaseEarnings = [
                'total_earned' => $totalEarned,
                'payable' => max(0, $payableRaw - $paid),
                'paid' => $paid,
            ];

            $recentPayouts = EmployeePayout::query()
                ->where('employee_id', $employee->id)
                ->whereNotNull('paid_at')
                ->orderByDesc('paid_at')
                ->limit(10)
                ->get([
                    'id',
                    'paid_at',
                    'reference',
                    'amount',
                    'currency',
                    'payout_method',
                    'metadata',
                ]);

            $recentAdvanceTransactions = EmployeePayout::query()
                ->where('employee_id', $employee->id)
                ->where('metadata->type', 'advance')
                ->whereNotNull('paid_at')
                ->orderByDesc('paid_at')
                ->limit(20)
                ->get([
                    'id',
                    'paid_at',
                    'amount',
                    'currency',
                    'payout_method',
                    'reference',
                    'note',
                    'metadata',
                ]);
        }

        if ($tab === 'projects') {
            $projects = Project::query()
                ->with('customer:id,name')
                ->whereHas('employees', fn ($query) => $query->whereKey($employee->id))
                ->orderByDesc('id')
                ->get();

            $projectStatusCounts = $projects->countBy('status');
            $projectIds = $projects->pluck('id');

            if ($projectIds->isNotEmpty()) {
                $projectTaskStatusCounts = ProjectTask::query()
                    ->select('project_id', 'status', DB::raw('COUNT(*) as total'))
                    ->whereIn('project_id', $projectIds)
                    ->where(function ($query) use ($employee) {
                        $query->where(function ($inner) use ($employee) {
                            $inner->where('assigned_type', 'employee')
                                ->where('assigned_id', $employee->id);
                        })->orWhereHas('assignments', function ($inner) use ($employee) {
                            $inner->where('assignee_type', 'employee')
                                ->where('assignee_id', $employee->id);
                        });
                    })
                    ->groupBy('project_id', 'status')
                    ->get()
                    ->groupBy('project_id')
                    ->map(fn ($rows) => $rows->pluck('total', 'status'));
            }
        }

        if (in_array($tab, ['payroll', 'timesheets'], true)) {
            $now = now();
            $today = $now->toDateString();
            $monthStart = $now->copy()->startOfMonth()->toDateString();
            $monthEnd = $now->copy()->endOfMonth()->toDateString();

            $workSessionQuery = EmployeeWorkSession::query()
                ->where('employee_id', $employee->id);

            $todayActiveSeconds = (int) (clone $workSessionQuery)
                ->whereDate('work_date', $today)
                ->sum('active_seconds');

            $monthActiveSeconds = (int) (clone $workSessionQuery)
                ->whereBetween('work_date', [$monthStart, $monthEnd])
                ->sum('active_seconds');

            $dailyActiveSeconds = (clone $workSessionQuery)
                ->selectRaw('work_date, SUM(active_seconds) as total_seconds')
                ->whereBetween('work_date', [$monthStart, $monthEnd])
                ->groupBy('work_date')
                ->pluck('total_seconds', 'work_date');

            $eligible = $workSummaryService->isEligible($employee);
            $requiredPerDay = $eligible ? $workSummaryService->requiredSeconds($employee) : 0;
            $monthRequiredSeconds = 0;
            $monthSalaryProjection = 0.0;
            $todaySalaryProjection = 0.0;

            foreach ($dailyActiveSeconds as $workDate => $seconds) {
                $seconds = (int) $seconds;
                $date = Carbon::parse((string) $workDate);

                if ($requiredPerDay > 0) {
                    $monthRequiredSeconds += $requiredPerDay;
                    $calculated = $workSummaryService->calculateAmount($employee, $date, $seconds);
                    $monthSalaryProjection += $calculated;

                    if ($date->toDateString() === $today) {
                        $todaySalaryProjection = $calculated;
                    }
                }
            }

            $coveragePercent = $monthRequiredSeconds > 0
                ? (int) round(min(100, ($monthActiveSeconds / $monthRequiredSeconds) * 100))
                : 0;

            $workSessionStats = [
                'eligible' => $eligible,
                'today_active_seconds' => $todayActiveSeconds,
                'month_active_seconds' => $monthActiveSeconds,
                'month_required_seconds' => $monthRequiredSeconds,
                'coverage_percent' => $coveragePercent,
                'today_salary_projection' => round($todaySalaryProjection, 2),
                'month_salary_projection' => round($monthSalaryProjection, 2),
                'currency' => $summary['currency'] ?? 'BDT',
            ];

            $payrollSourceNote = ($summary['salary_type'] ?? null) === 'hourly'
                ? 'Hourly payroll is generated from remote work-session hours for the selected payroll period.'
                : 'Monthly payroll uses compensation, pro-rata rules, and unpaid leave. Work-session data is shown for monitoring and projection.';

            $recentWorkSessions = (clone $workSessionQuery)
                ->orderByDesc('work_date')
                ->orderByDesc('started_at')
                ->limit(20)
                ->get([
                    'work_date',
                    'started_at',
                    'ended_at',
                    'last_activity_at',
                    'active_seconds',
                ]);

            $recentWorkSummaries = EmployeeWorkSummary::query()
                ->where('employee_id', $employee->id)
                ->orderByDesc('work_date')
                ->limit(15)
                ->get([
                    'work_date',
                    'active_seconds',
                    'required_seconds',
                    'generated_salary_amount',
                    'status',
                ]);

            $recentPayrollItems = PayrollItem::query()
                ->with('period:id,period_key,start_date,end_date')
                ->where('employee_id', $employee->id)
                ->orderByDesc('id')
                ->limit(10)
                ->get([
                    'id',
                    'payroll_period_id',
                    'status',
                    'pay_type',
                    'currency',
                    'base_pay',
                    'timesheet_hours',
                    'overtime_enabled',
                    'overtime_hours',
                    'overtime_rate',
                    'bonuses',
                    'penalties',
                    'advances',
                    'deductions',
                    'gross_pay',
                    'net_pay',
                    'paid_at',
                ]);

            if ($tab === 'payroll') {
                $periodPayrollStats = [];
                foreach ($recentPayrollItems as $payrollItem) {
                    $period = $payrollItem->period;
                    if (! $period || ! $period->start_date || ! $period->end_date) {
                        $basePay = round((float) ($payrollItem->base_pay ?? 0), 2, PHP_ROUND_HALF_UP);
                        $bonus = $this->sumPayrollAdjustment($payrollItem->bonuses);
                        $penalty = $this->sumPayrollAdjustment($payrollItem->penalties);
                        $advance = $this->sumPayrollAdjustment($payrollItem->advances);
                        $deduction = $this->sumPayrollAdjustment($payrollItem->deductions);
                        $overtimeHours = (float) ($payrollItem->overtime_hours ?? 0);
                        $overtimeRate = (float) ($payrollItem->overtime_rate ?? 0);
                        $overtimePay = round($overtimeHours * $overtimeRate, 2, PHP_ROUND_HALF_UP);

                        $payrollItem->computed_base_pay = $basePay;
                        $payrollItem->computed_hours_attendance = number_format((float) ($payrollItem->timesheet_hours ?? 0), 2).' hrs';
                        $payrollItem->computed_overtime_label = number_format($overtimeHours, 2).($overtimeRate > 0 ? ' @ '.number_format($overtimeRate, 2) : '');
                        $payrollItem->computed_overtime_pay = $overtimePay;
                        $payrollItem->computed_bonus = round($bonus, 2, PHP_ROUND_HALF_UP);
                        $payrollItem->computed_penalty = round($penalty, 2, PHP_ROUND_HALF_UP);
                        $payrollItem->computed_advance = round($advance, 2, PHP_ROUND_HALF_UP);
                        $payrollItem->computed_deduction = round($deduction, 2, PHP_ROUND_HALF_UP);
                        $payrollItem->computed_est_subtotal = $basePay;
                        $payrollItem->computed_gross_pay = round($basePay + $overtimePay + $bonus, 2, PHP_ROUND_HALF_UP);
                        $payrollItem->computed_net_pay = round($payrollItem->computed_gross_pay - $penalty - $advance - $deduction, 2, PHP_ROUND_HALF_UP);

                        continue;
                    }

                    $periodId = (int) $period->id;
                    if (! isset($periodPayrollStats[$periodId])) {
                        $startDate = $period->start_date->toDateString();
                        $endDate = $period->end_date->toDateString();

                        $paidHolidayDates = PaidHoliday::query()
                            ->where('is_paid', true)
                            ->whereBetween('holiday_date', [$startDate, $endDate])
                            ->pluck('holiday_date')
                            ->map(fn ($date) => Carbon::parse((string) $date)->toDateString())
                            ->all();

                        $paidHolidayMap = array_fill_keys($paidHolidayDates, true);
                        $workingDaysInPeriod = 0;
                        $cursor = $period->start_date->copy()->startOfDay();
                        $periodEnd = $period->end_date->copy()->startOfDay();
                        while ($cursor->lessThanOrEqualTo($periodEnd)) {
                            if (! isset($paidHolidayMap[$cursor->toDateString()])) {
                                $workingDaysInPeriod++;
                            }
                            $cursor->addDay();
                        }

                        $totalWorkSeconds = (int) EmployeeWorkSession::query()
                            ->where('employee_id', $employee->id)
                            ->whereDate('work_date', '>=', $startDate)
                            ->whereDate('work_date', '<=', $endDate)
                            ->when(! empty($paidHolidayDates), fn ($q) => $q->whereNotIn('work_date', $paidHolidayDates))
                            ->sum('active_seconds');
                        $workLogHours = round($totalWorkSeconds / 3600, 2, PHP_ROUND_HALF_UP);

                        $attendanceRows = EmployeeAttendance::query()
                            ->where('employee_id', $employee->id)
                            ->whereDate('date', '>=', $startDate)
                            ->whereDate('date', '<=', $endDate)
                            ->when(! empty($paidHolidayDates), fn ($q) => $q->whereNotIn('date', $paidHolidayDates))
                            ->get(['status']);
                        $presentDays = (float) $attendanceRows->where('status', 'present')->count();
                        $halfDays = (float) $attendanceRows->where('status', 'half_day')->count() * 0.5;
                        $workedDays = round($presentDays + $halfDays, 1);

                        $periodPayrollStats[$periodId] = [
                            'work_log_hours' => $workLogHours,
                            'worked_days' => $workedDays,
                            'working_days' => $workingDaysInPeriod,
                        ];
                    }

                    $periodStats = $periodPayrollStats[$periodId];
                    $employmentType = (string) ($employee->employment_type ?? '');
                    $isHoursBased = $payrollItem->pay_type === 'hourly' || $employmentType === 'part_time';
                    $isAttendanceBased = $employmentType === 'full_time';
                    $hoursPerDay = $employmentType === 'part_time' ? 4 : 8;

                    $actualHours = (float) ($periodStats['work_log_hours'] ?? 0);
                    if ($actualHours <= 0) {
                        $actualHours = (float) ($payrollItem->timesheet_hours ?? 0);
                    }

                    $workedDays = (float) ($periodStats['worked_days'] ?? 0);
                    $totalWorkingDays = (int) ($periodStats['working_days'] ?? 0);
                    $expectedHours = max(0, $totalWorkingDays) * $hoursPerDay;

                    $basePay = (float) ($payrollItem->base_pay ?? 0);
                    $estSubtotal = $basePay;
                    if ($isHoursBased) {
                        $hoursRatio = $expectedHours > 0 ? min(1, max(0, $actualHours / $expectedHours)) : 0;
                        $estSubtotal = $basePay * $hoursRatio;
                    } elseif ($isAttendanceBased && $totalWorkingDays > 0) {
                        $attendanceRatio = min(1, max(0, $workedDays / $totalWorkingDays));
                        $estSubtotal = $basePay * $attendanceRatio;
                    }
                    $estSubtotal = round($estSubtotal, 2, PHP_ROUND_HALF_UP);

                    $bonus = $this->sumPayrollAdjustment($payrollItem->bonuses);
                    $penalty = $this->sumPayrollAdjustment($payrollItem->penalties);
                    $advance = $this->sumPayrollAdjustment($payrollItem->advances);
                    $deduction = $this->sumPayrollAdjustment($payrollItem->deductions);
                    $overtimeHours = (float) ($payrollItem->overtime_hours ?? 0);
                    $overtimeRate = (float) ($payrollItem->overtime_rate ?? 0);
                    $overtimePay = $overtimeHours * $overtimeRate;

                    if ($isHoursBased) {
                        $hoursAttendance = number_format($actualHours, 2).' hrs ('.number_format((float) $expectedHours, 0).' hrs)';
                    } elseif ($isAttendanceBased) {
                        $workedDisplay = fmod($workedDays, 1.0) === 0.0
                            ? (string) ((int) $workedDays)
                            : number_format($workedDays, 1);
                        $hoursAttendance = $workedDisplay.' ('.$totalWorkingDays.')';
                    } else {
                        $hoursAttendance = '--';
                    }

                    $payrollItem->computed_base_pay = round($basePay, 2, PHP_ROUND_HALF_UP);
                    $payrollItem->computed_hours_attendance = $hoursAttendance;
                    $payrollItem->computed_overtime_label = number_format($overtimeHours, 2).($overtimeRate > 0 ? ' @ '.number_format($overtimeRate, 2) : '');
                    $payrollItem->computed_overtime_pay = round($overtimePay, 2, PHP_ROUND_HALF_UP);
                    $payrollItem->computed_bonus = round($bonus, 2, PHP_ROUND_HALF_UP);
                    $payrollItem->computed_penalty = round($penalty, 2, PHP_ROUND_HALF_UP);
                    $payrollItem->computed_advance = round($advance, 2, PHP_ROUND_HALF_UP);
                    $payrollItem->computed_deduction = round($deduction, 2, PHP_ROUND_HALF_UP);
                    $payrollItem->computed_est_subtotal = $estSubtotal;
                    $payrollItem->computed_gross_pay = round($estSubtotal + $overtimePay + $bonus, 2, PHP_ROUND_HALF_UP);
                    $payrollItem->computed_net_pay = round($payrollItem->computed_gross_pay - $penalty - $advance - $deduction, 2, PHP_ROUND_HALF_UP);
                }
            }

            $draftPayrollItem = $recentPayrollItems->firstWhere('status', 'draft');

            if ($tab === 'payroll' && in_array($summary['salary_type'] ?? null, ['monthly', 'hourly'], true)) {
                $recentSalaryAdvances = EmployeePayout::query()
                    ->where('employee_id', $employee->id)
                    ->where('metadata->type', 'advance')
                    ->where('metadata->advance_scope', 'payroll')
                    ->whereNotNull('paid_at')
                    ->orderByDesc('paid_at')
                    ->limit(20)
                    ->get([
                        'id',
                        'paid_at',
                        'amount',
                        'currency',
                        'payout_method',
                        'reference',
                        'note',
                        'metadata',
                    ]);
            }
        }

        return Inertia::render('Admin/Hr/Employees/Show', [
            'pageTitle' => $employee->name,
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'status' => $employee->status,
                'department' => $employee->department,
                'designation' => $employee->designation,
                'employment_type' => $employee->employment_type,
                'work_mode' => $employee->work_mode,
                'address' => $employee->address,
                'join_date' => $employee->join_date?->format(config('app.date_format', 'd-m-Y')),
                'photo_path' => $employee->photo_path,
                'nid_path' => $employee->nid_path,
                'cv_path' => $employee->cv_path,
                'manager' => [
                    'id' => $employee->manager?->id,
                    'name' => $employee->manager?->name,
                ],
                'user' => [
                    'id' => $employee->user?->id,
                    'name' => $employee->user?->name,
                    'email' => $employee->user?->email,
                    'avatar_path' => $employee->user?->avatar_path,
                ],
                'active_compensation' => [
                    'effective_from' => $employee->activeCompensation?->effective_from?->format(config('app.date_format', 'd-m-Y')),
                ],
            ],
            'tab' => $tab,
            'summary' => $summary,
            'projects' => $projects->values(),
            'projectStatusCounts' => $projectStatusCounts,
            'projectTaskStatusCounts' => $projectTaskStatusCounts,
            'taskSummary' => $taskSummary,
            'subtaskSummary' => $subtaskSummary,
            'taskProgress' => $taskProgress,
            'projectBaseEarnings' => $projectBaseEarnings,
            'recentEarnings' => $recentEarnings->values(),
            'recentPayouts' => $recentPayouts->values(),
            'advanceProjects' => $advanceProjects->values(),
            'recentWorkSessions' => $recentWorkSessions->values(),
            'recentWorkSummaries' => $recentWorkSummaries->values(),
            'recentPayrollItems' => $recentPayrollItems->values(),
            'recentSalaryAdvances' => $recentSalaryAdvances->values(),
            'recentAdvanceTransactions' => $recentAdvanceTransactions->values(),
            'draftPayrollItem' => $draftPayrollItem,
            'workSessionStats' => $workSessionStats,
            'payrollSourceNote' => $payrollSourceNote,
            'paymentMethods' => PaymentMethod::dropdownOptions()
                ->map(fn ($method) => [
                    'code' => $method->code,
                    'name' => $method->name,
                ])
                ->values(),
            'documentLinks' => [
                'nid' => $employee->nid_path ? route('admin.user-documents.show', ['type' => 'employee', 'id' => $employee->id, 'doc' => 'nid']) : null,
                'cv' => $employee->cv_path ? route('admin.user-documents.show', ['type' => 'employee', 'id' => $employee->id, 'doc' => 'cv']) : null,
            ],
            'routes' => [
                'index' => route('admin.hr.employees.index', [], false),
                'edit' => route('admin.hr.employees.edit', $employee, false),
                'impersonate' => route('admin.hr.employees.impersonate', $employee, false),
                'show' => route('admin.hr.employees.show', $employee, false),
                'advancePayout' => route('admin.hr.employees.advance-payout', $employee, false),
                'payoutProof' => route('admin.hr.employee-payouts.proof', ['employeePayout' => '__ID__'], false),
            ],
        ]);
    }

    public function impersonate(Request $request, Employee $employee): RedirectResponse
    {
        if ($request->session()->has('impersonator_id')) {
            return back()->withErrors(['impersonate' => 'You are already impersonating another account. Stop impersonation first.']);
        }

        if ($employee->status !== 'active') {
            return back()->withErrors(['impersonate' => 'Employee is not active.']);
        }

        $user = $employee->user;
        if (! $user) {
            $user = User::where('email', $employee->email)->first();

            if ($user && $user->employee) {
                return back()->withErrors(['impersonate' => 'This email is already linked to another employee user.']);
            }

            if (! $user) {
                $user = User::create([
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'password' => Str::random(32),
                    'role' => Role::EMPLOYEE,
                    'customer_id' => null,
                ]);
            }

            $employee->update(['user_id' => $user->id]);
        }

        $request->session()->put('impersonator_id', $request->user()->id);
        Auth::login($user);
        Auth::guard('employee')->login($user);
        $request->session()->regenerate();

        return redirect()->route('employee.dashboard');
    }

    private function handleUploads(Request $request): array
    {
        $paths = [];

        if ($request->hasFile('nid_file')) {
            $paths['nid_path'] = $request->file('nid_file')->store('employees/nid', 'public');
        }

        if ($request->hasFile('photo')) {
            $paths['photo_path'] = $request->file('photo')->store('employees/photos', 'public');
        }

        if ($request->hasFile('cv_file')) {
            $paths['cv_path'] = $request->file('cv_file')->store('employees/cv', 'public');
        }

        return $paths;
    }

    private function resolveEmployeeLoginStatuses($employees): array
    {
        $employeeIds = $employees->pluck('id')->all();
        if (empty($employeeIds)) {
            return [];
        }

        $openSessions = EmployeeSession::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereNull('logout_at')
            ->orderByDesc('last_seen_at')
            ->get()
            ->groupBy('employee_id');

        $threshold = now()->subMinutes(2);
        $statuses = [];

        foreach ($employeeIds as $employeeId) {
            $session = $openSessions->get($employeeId)?->first();
            if (! $session) {
                $statuses[$employeeId] = 'logout';

                continue;
            }

            $lastSeen = $session->last_seen_at;
            $statuses[$employeeId] = $lastSeen && $lastSeen->greaterThanOrEqualTo($threshold)
                ? 'login'
                : 'idle';
        }

        return $statuses;
    }

    private function resolveEmployeeLastLogins($employees): array
    {
        $employeeIds = $employees->pluck('id')->all();
        if (empty($employeeIds)) {
            return [];
        }

        return EmployeeSession::query()
            ->select('employee_id', DB::raw('MAX(login_at) as last_login_at'))
            ->whereIn('employee_id', $employeeIds)
            ->groupBy('employee_id')
            ->pluck('last_login_at', 'employee_id')
            ->all();
    }

    private function sumPayrollAdjustment($value): float
    {
        if (is_array($value)) {
            return (float) array_reduce($value, function ($carry, $row) {
                return $carry + (float) ($row['amount'] ?? $row ?? 0);
            }, 0.0);
        }

        return (float) ($value ?? 0);
    }
}
