<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Services\EmployeeWorkSummaryService;
use App\Services\TaskQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    public function __invoke(Request $request, EmployeeWorkSummaryService $workSummaryService, TaskQueryService $taskQueryService): InertiaResponse
    {
        $employee = $request->attributes->get('employee') ?: $request->user()?->employee;
        if ($employee) {
            $employee->loadMissing([
                'manager:id,name',
                'user:id,name,email',
            ]);
        }

        $employeeId = $employee?->id;

        $projectRelation = $employeeId
            ? Project::query()->whereHas('employees', fn ($query) => $query->whereKey($employeeId))
            : Project::query()->whereRaw('0 = 1');

        $projectIds = (clone $projectRelation)->pluck('id');
        $totalProjects = $projectIds->count();

        $projectStatusCounts = (clone $projectRelation)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $recentProjects = (clone $projectRelation)
            ->with(['customer'])
            ->withCount('tasks')
            ->orderByDesc('updated_at')
            ->limit(4)
            ->get();

        $taskStatusCounts = $employeeId
            ? ProjectTask::query()
                ->select('status', DB::raw('COUNT(*) as total'))
                ->where('assigned_type', 'employee')
                ->where('assigned_id', $employeeId)
                ->groupBy('status')
                ->pluck('total', 'status')
            : collect();

        $taskStats = [
            'total' => (int) $taskStatusCounts->sum(),
            'in_progress' => (int) ($taskStatusCounts['in_progress'] ?? 0),
            'completed' => (int) (($taskStatusCounts['done'] ?? 0) + ($taskStatusCounts['completed'] ?? 0)),
        ];

        $contractSummary = null;
        $contractProjects = collect();

        if ($employeeId && $employee?->employment_type === 'contract') {
            $contractProjectsQuery = Project::query()
                ->whereHas('employees', fn ($query) => $query->whereKey($employeeId))
                ->whereNotNull('contract_employee_total_earned');

            $contractSummary = [
                'total_earned' => (float) (clone $contractProjectsQuery)->sum('contract_employee_total_earned'),
                'payable' => (float) (clone $contractProjectsQuery)->sum('contract_employee_payable'),
            ];

            $contractProjects = (clone $contractProjectsQuery)
                ->orderByDesc('updated_at')
                ->limit(5)
                ->get([
                    'id',
                    'name',
                    'status',
                    'contract_employee_total_earned',
                    'contract_employee_payable',
                    'currency',
                ]);
        }

        $workSessionEligible = false;
        $workSessionRequiredSeconds = null;

        if ($employee) {
            $workSessionEligible = $workSummaryService->isEligible($employee);
            if ($workSessionEligible) {
                $workSessionRequiredSeconds = $workSummaryService->requiredSeconds($employee);
            }
        }

        $user = $request->user();
        $showTasksWidget = $taskQueryService->canViewTasks($user);
        $tasksWidget = $showTasksWidget ? $taskQueryService->dashboardTasksForUser($user) : null;

        $dateFormat = config('app.date_format', 'd-m-Y');

        return Inertia::render('Employee/Dashboard/Index', [
            'employee' => $employee ? [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email ?: $employee->user?->email,
                'phone' => $employee->phone,
                'status' => $employee->status,
                'department' => $employee->department,
                'designation' => $employee->designation,
                'manager_name' => $employee->manager?->name,
                'employment_type' => $employee->employment_type,
                'work_mode' => $employee->work_mode,
                'join_date_display' => $employee->join_date?->format($dateFormat),
                'address' => $employee->address,
            ] : null,
            'project_stats' => [
                'total' => $totalProjects,
                'status_counts' => $projectStatusCounts,
            ],
            'recent_projects' => $recentProjects->map(function (Project $project) use ($dateFormat) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'status_label' => ucfirst(str_replace('_', ' ', (string) $project->status)),
                    'tasks_count' => (int) ($project->tasks_count ?? 0),
                    'due_date_display' => $project->due_date?->format($dateFormat) ?? '--',
                    'routes' => [
                        'show' => route('employee.projects.show', $project),
                    ],
                ];
            })->values()->all(),
            'task_stats' => $taskStats,
            'contract_summary' => $contractSummary,
            'contract_projects' => $contractProjects->map(function (Project $project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'status_label' => ucfirst(str_replace('_', ' ', (string) $project->status)),
                    'total_earned' => (float) ($project->contract_employee_total_earned ?? 0),
                    'payable' => (float) ($project->contract_employee_payable ?? 0),
                    'currency' => $project->currency,
                    'routes' => [
                        'show' => route('employee.projects.show', $project),
                    ],
                ];
            })->values()->all(),
            'work_session' => [
                'eligible' => $workSessionEligible,
                'required_seconds' => $workSessionRequiredSeconds,
                'routes' => [
                    'start' => route('employee.work-sessions.start'),
                    'ping' => route('employee.work-sessions.ping'),
                    'stop' => route('employee.work-sessions.stop'),
                    'summary' => route('employee.work-summaries.today'),
                ],
            ],
            'tasks_widget' => [
                'show' => $showTasksWidget,
                'summary' => $tasksWidget['summary'] ?? null,
                'open_tasks' => ($tasksWidget['openTasks'] ?? collect())->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'status' => $task->status,
                        'project_name' => $task->project?->name,
                        'routes' => [
                            'show' => $task->project ? route('employee.projects.tasks.show', [$task->project, $task]) : null,
                        ],
                    ];
                })->values()->all(),
                'in_progress_tasks' => ($tasksWidget['inProgressTasks'] ?? collect())->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'status' => $task->status,
                        'project_name' => $task->project?->name,
                        'routes' => [
                            'show' => $task->project ? route('employee.projects.tasks.show', [$task->project, $task]) : null,
                        ],
                    ];
                })->values()->all(),
            ],
            'routes' => [
                'projects_index' => route('employee.projects.index'),
                'tasks_index' => route('employee.tasks.index'),
                'logout' => route('logout'),
            ],
        ]);
    }
}
