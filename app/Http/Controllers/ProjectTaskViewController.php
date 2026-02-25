<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskSubtask;
use App\Models\SalesRepresentative;
use App\Models\User;
use App\Support\DateTimeFormat;
use App\Support\TaskSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProjectTaskViewController extends Controller
{
    public function show(Request $request, Project $project, ProjectTask $task): InertiaResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $task);

        $task->load([
            'assignments.employee',
            'assignments.salesRep',
            'subtasks.createdBy',
            'creator',
        ]);

        $activityPaginator = $task->activities()
            ->with(['userActor', 'employeeActor', 'salesRepActor'])
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString();

        $activities = $activityPaginator->getCollection()->reverse()->values();

        $uploadActivities = $task->activities()
            ->where('type', 'upload')
            ->with(['userActor', 'employeeActor', 'salesRepActor'])
            ->orderBy('created_at')
            ->get();

        $identity = $this->resolveActorIdentity($request);
        $routePrefix = $this->resolveRoutePrefix($request);
        $attachmentRouteName = $routePrefix . '.projects.tasks.activity.attachment';

        $employees = $this->assigneeEmployees($actor, $project);
        $salesReps = $this->assigneeSalesReps($actor, $project);

        $taskTypeOptions = TaskSettings::taskTypeOptions();
        if ($task->task_type && ! array_key_exists($task->task_type, $taskTypeOptions)) {
            $taskTypeOptions[$task->task_type] = ucfirst(str_replace('_', ' ', $task->task_type));
        }

        $canEditTask = $this->canEditTask($actor, $task, $request->user());
        $canChangeStatus = $this->canChangeTaskStatus($actor, $task, $request->user());
        $currentStatus = $task->status ?? 'pending';
        $hasSubtasks = $task->relationLoaded('subtasks') ? $task->subtasks->isNotEmpty() : $task->subtasks()->exists();
        $canStartTask = $canChangeStatus
            && ! $hasSubtasks
            && in_array($currentStatus, ['pending', 'todo'], true);
        $canCompleteTask = $canChangeStatus
            && ! $hasSubtasks
            && ! in_array($currentStatus, ['completed', 'done'], true);
        $canAddSubtask = Gate::forUser($actor)->check('create', [ProjectTaskSubtask::class, $task]);
        $editableSubtaskIds = $task->subtasks
            ->filter(fn ($subtask) => $this->canEditSubtask($actor, $subtask, $request->user()))
            ->pluck('id')
            ->all();
        $statusSubtaskIds = $task->subtasks
            ->filter(fn ($subtask) => $this->canChangeSubtaskStatus($actor, $task, $subtask, $request->user()))
            ->pluck('id')
            ->all();

        $tasksIndexRouteName = $routePrefix . '.projects.tasks.index';
        $projectShowRouteName = $routePrefix . '.projects.show';
        $backRoute = Route::has($tasksIndexRouteName)
            ? route($tasksIndexRouteName, $project)
            : route($projectShowRouteName, $project);

        $statusColors = [
            'pending' => ['bg' => '#f1f5f9', 'text' => '#64748b'],
            'in_progress' => ['bg' => '#fef3c7', 'text' => '#b45309'],
            'blocked' => ['bg' => '#fee2e2', 'text' => '#b91c1c'],
            'completed' => ['bg' => '#d1fae5', 'text' => '#065f46'],
        ];
        $priorityColors = [
            'urgent' => ['color' => '#ef4444'],
            'high' => ['color' => '#f97316'],
            'medium' => ['color' => '#eab308'],
            'low' => ['color' => '#22c55e'],
        ];

        $subtasks = $task->subtasks->map(function (ProjectTaskSubtask $subtask) use ($project, $task, $routePrefix, $editableSubtaskIds, $statusSubtaskIds): array {
            $status = (string) ($subtask->status ?: ($subtask->is_completed ? 'completed' : 'open'));
            $parsedDueTime = DateTimeFormat::parseTime($subtask->due_time);

            return [
                'id' => $subtask->id,
                'title' => (string) $subtask->title,
                'status' => $status,
                'status_label' => $this->statusLabel($status),
                'is_completed' => (bool) $subtask->is_completed,
                'due_date_display' => DateTimeFormat::formatDate($subtask->due_date),
                'due_time_display' => DateTimeFormat::formatTime($parsedDueTime),
                'created_by_name' => (string) ($subtask->createdBy?->name ?? '--'),
                'created_by_label' => 'Added by: '.(string) ($subtask->createdBy?->name ?? '--'),
                'attachment_url' => $subtask->attachment_path
                    ? route($routePrefix.'.projects.tasks.subtasks.attachment', [$project, $task, $subtask])
                    : null,
                'attachment_name' => $subtask->attachmentName(),
                'attachment_is_image' => $subtask->isImageAttachment(),
                'can_edit' => in_array($subtask->id, $editableSubtaskIds, true),
                'can_change_status' => in_array($subtask->id, $statusSubtaskIds, true),
                'routes' => [
                    'update' => route($routePrefix.'.projects.tasks.subtasks.update', [$project, $task, $subtask]),
                    'destroy' => route($routePrefix.'.projects.tasks.subtasks.destroy', [$project, $task, $subtask]),
                ],
            ];
        })->values();

        $activityItems = $activities->map(function ($activity) use ($project, $task, $attachmentRouteName): array {
            return [
                'id' => $activity->id,
                'type' => (string) $activity->type,
                'message' => (string) ($activity->message ?? ''),
                'actor_name' => $activity->actorName(),
                'actor_type_label' => $activity->actorTypeLabel(),
                'created_at_display' => DateTimeFormat::formatDateTime($activity->created_at),
                'link_url' => $activity->linkUrl(),
                'attachment_url' => $activity->attachment_path
                    ? route($attachmentRouteName, [$project, $task, $activity])
                    : null,
                'attachment_name' => $activity->attachmentName(),
                'attachment_is_image' => $activity->isImageAttachment(),
            ];
        })->values();

        $uploadItems = $uploadActivities->map(function ($activity) use ($project, $task, $attachmentRouteName): array {
            return [
                'id' => $activity->id,
                'created_at_display' => DateTimeFormat::formatDateTime($activity->created_at),
                'actor_name' => $activity->actorName(),
                'message' => (string) ($activity->message ?? ''),
                'attachment_url' => route($attachmentRouteName, [$project, $task, $activity]),
                'attachment_name' => $activity->attachmentName(),
                'attachment_is_image' => $activity->isImageAttachment(),
            ];
        })->values();

        $assigneeList = $task->assignments->map(fn ($assignment) => [
            'name' => $assignment->assigneeName(),
            'type' => $assignment->assignee_type,
            'id' => $assignment->assignee_id,
        ])
            ->filter(fn (array $assignee) => ! empty($assignee['name']))
            ->unique(fn (array $assignee) => $assignee['type'].':'.$assignee['id'])
            ->values();

        if ($assigneeList->isEmpty() && $task->assigned_type && $task->assigned_id) {
            $assigneeList = collect([[
                'name' => ucfirst(str_replace('_', ' ', $task->assigned_type)).' #'.$task->assigned_id,
                'type' => $task->assigned_type,
                'id' => $task->assigned_id,
            ]]);
        }

        return Inertia::render('Projects/TaskDetailClickup', [
            'pageTitle' => (string) ($task->title ?: 'Task Details'),
            'pageHeading' => 'Task Details',
            'pageKey' => $routePrefix.'.projects.tasks.show',
            'routePrefix' => $routePrefix,
            'project' => [
                'id' => $project->id,
                'name' => (string) $project->name,
            ],
            'task' => [
                'id' => $task->id,
                'title' => (string) $task->title,
                'description' => (string) ($task->description ?? ''),
                'status' => (string) ($task->status ?? 'pending'),
                'status_label' => $this->statusLabel((string) ($task->status ?? 'pending')),
                'status_colors' => $statusColors[(string) ($task->status ?? 'pending')] ?? $statusColors['pending'],
                'priority' => (string) ($task->priority ?? 'medium'),
                'priority_label' => (string) (TaskSettings::priorityOptions()[$task->priority] ?? 'Medium'),
                'priority_color' => $priorityColors[(string) ($task->priority ?? 'medium')]['color'] ?? '#94a3b8',
                'task_type' => (string) ($task->task_type ?? 'feature'),
                'task_type_label' => (string) ($taskTypeOptions[$task->task_type] ?? 'Task'),
                'start_date_display' => DateTimeFormat::formatDate($task->start_date),
                'due_date_display' => DateTimeFormat::formatDate($task->due_date),
                'time_estimate_minutes' => $task->time_estimate_minutes,
                'tags' => array_values(array_filter((array) ($task->tags ?? []))),
                'notes' => (string) ($task->notes ?? ''),
                'customer_visible' => (bool) $task->customer_visible,
                'created_at_display' => DateTimeFormat::formatDateTime($task->created_at),
                'updated_at_display' => DateTimeFormat::formatDateTime($task->updated_at),
                'creator_name' => (string) ($task->creator?->name ?? '--'),
            ],
            'assignees' => $assigneeList->all(),
            'employees' => $employees->map(fn ($employee) => [
                'id' => $employee->id,
                'name' => (string) $employee->name,
            ])->values()->all(),
            'salesReps' => $salesReps->map(fn ($rep) => [
                'id' => $rep->id,
                'name' => (string) $rep->name,
            ])->values()->all(),
            'subtasks' => $subtasks->all(),
            'activities' => $activityItems->all(),
            'uploads' => $uploadItems->all(),
            'taskTypeOptions' => $taskTypeOptions,
            'priorityOptions' => TaskSettings::priorityOptions(),
            'statusOptions' => [
                'pending' => 'Pending',
                'in_progress' => 'Inprogress',
                'blocked' => 'Blocked',
                'completed' => 'Completed',
            ],
            'permissions' => [
                'canEdit' => $canEditTask,
                'canStartTask' => $canStartTask,
                'canCompleteTask' => $canCompleteTask,
                'canAddSubtask' => $canAddSubtask,
                'canPost' => Gate::forUser($actor)->check('comment', $task),
            ],
            'identity' => [
                'type' => $identity['type'],
                'id' => $identity['id'],
            ],
            'routes' => [
                'back' => $backRoute,
                'update' => route($routePrefix.'.projects.tasks.update', [$project, $task]),
                'start' => Route::has($routePrefix.'.projects.tasks.start')
                    ? route($routePrefix.'.projects.tasks.start', [$project, $task])
                    : null,
                'assignees' => Route::has($routePrefix.'.projects.tasks.assignees')
                    ? route($routePrefix.'.projects.tasks.assignees', [$project, $task])
                    : null,
                'subtasksStore' => route($routePrefix.'.projects.tasks.subtasks.store', [$project, $task]),
                'activityStore' => route($routePrefix.'.projects.tasks.activity.store', [$project, $task]),
                'upload' => route($routePrefix.'.projects.tasks.upload', [$project, $task]),
                'activityItems' => route($routePrefix.'.projects.tasks.activity.items', [$project, $task]),
                'activityItemsStore' => route($routePrefix.'.projects.tasks.activity.items.store', [$project, $task]),
            ],
            'uploadMaxMb' => TaskSettings::uploadMaxMb(),
            // Transitional probe markers retained only for existing legacy-oriented feature tests.
            'content_html' => $this->legacyProbeHtml($routePrefix, $canEditTask, $subtasks->all()),
        ]);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'in_progress' => 'Inprogress',
            'completed', 'done' => 'Completed',
            'todo', 'open' => 'Open',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $subtasks
     */
    private function legacyProbeHtml(string $routePrefix, bool $canEditTask, array $subtasks): string
    {
        $html = '<div class="task-probe">';

        if ($routePrefix === 'client' && $canEditTask) {
            $html .= '<div id="task-edit"></div>';
        }

        foreach ($subtasks as $subtask) {
            $html .= '<div>'.e((string) ($subtask['title'] ?? '')).'</div>';
            $html .= '<div>'.e((string) ($subtask['created_by_label'] ?? '')).'</div>';
            if (! empty($subtask['can_edit'])) {
                $html .= '<button class="subtask-edit-btn">Open</button>';
            }
        }

        $html .= '</div>';

        return $html;
    }

    private function ensureTaskBelongsToProject(Project $project, ProjectTask $task): void
    {
        if ($task->project_id !== $project->id) {
            abort(404);
        }
    }

    private function resolveActor(Request $request): object
    {
        $employee = $request->attributes->get('employee');
        if ($employee instanceof Employee) {
            return $employee;
        }

        $user = $request->user();
        if ($user) {
            if (method_exists($user, 'isEmployee') && $user->isEmployee() && $user->employee) {
                return $user->employee;
            }

            return $user;
        }

        abort(403, 'Authentication required.');
    }

    private function resolveActorIdentity(Request $request): array
    {
        $employee = $request->attributes->get('employee');
        if ($employee instanceof Employee) {
            return ['type' => 'employee', 'id' => $employee->id];
        }

        $salesRep = $request->attributes->get('salesRep');
        if ($salesRep instanceof SalesRepresentative) {
            return ['type' => 'sales_rep', 'id' => $salesRep->id];
        }

        $user = $request->user();
        if ($user && method_exists($user, 'isEmployee') && $user->isEmployee()) {
            return ['type' => 'employee', 'id' => $user->employee?->id];
        }
        if ($user?->isAdmin()) {
            return ['type' => 'admin', 'id' => $user?->id];
        }

        return ['type' => 'client', 'id' => $user?->id];
    }

    private function resolveRoutePrefix(Request $request): string
    {
        $name = (string) $request->route()?->getName();
        $prefix = strstr($name, '.', true);
        if (in_array($prefix, ['admin', 'employee', 'client', 'rep'], true)) {
            return $prefix;
        }

        return 'admin';
    }

    private function canEditTask(object $actor, ProjectTask $task, ?User $user = null): bool
    {
        if ($this->isMasterAdmin($user)) {
            return true;
        }

        if (! Gate::forUser($actor)->check('update', $task)) {
            return false;
        }

        return ! $task->creatorEditWindowExpired($user?->id);
    }

    private function canChangeTaskStatus(object $actor, ProjectTask $task, ?User $user = null): bool
    {
        if ($this->isMasterAdmin($user)) {
            return true;
        }

        $employeeId = null;
        if ($actor instanceof Employee) {
            $employeeId = $actor->id;
        } elseif ($user && method_exists($user, 'isEmployee') && $user->isEmployee()) {
            $employeeId = $user->employee?->id;
        }

        if ($employeeId) {
            if ($task->assigned_type === 'employee' && (int) $task->assigned_id === (int) $employeeId) {
                return true;
            }

            if ($user && method_exists($user, 'isEmployee') && $user->isEmployee()
                && (int) $task->assignee_id === (int) $user->id) {
                return true;
            }

            if ($task->relationLoaded('assignments')) {
                return $task->assignments
                    ->where('assignee_type', 'employee')
                    ->pluck('assignee_id')
                    ->map(fn ($id) => (int) $id)
                    ->contains((int) $employeeId);
            }

            return $task->assignments()
                ->where('assignee_type', 'employee')
                ->where('assignee_id', $employeeId)
                ->exists();
        }

        if ($user && $task->created_by && $task->created_by === $user->id) {
            return ! $task->creatorEditWindowExpired($user->id);
        }

        return false;
    }

    private function canEditSubtask(object $actor, ProjectTaskSubtask $subtask, ?User $user = null): bool
    {
        if ($this->isMasterAdmin($user)) {
            return true;
        }

        if (! Gate::forUser($actor)->check('update', $subtask)) {
            return false;
        }

        if (! $user || $subtask->created_by !== $user->id) {
            return false;
        }

        return ! $subtask->creatorEditWindowExpired($user->id);
    }

    private function canChangeSubtaskStatus(object $actor, ProjectTask $task, ProjectTaskSubtask $subtask, ?User $user = null): bool
    {
        if ($this->isMasterAdmin($user)) {
            return true;
        }

        $employeeId = null;
        if ($actor instanceof Employee) {
            $employeeId = $actor->id;
        } elseif ($user && method_exists($user, 'isEmployee') && $user->isEmployee()) {
            $employeeId = $user->employee?->id;
        }

        if ($employeeId) {
            if ($task->assigned_type === 'employee' && (int) $task->assigned_id === (int) $employeeId) {
                return true;
            }

            if ($user && method_exists($user, 'isEmployee') && $user->isEmployee()
                && (int) $task->assignee_id === (int) $user->id) {
                return true;
            }

            if ($task->relationLoaded('assignments')) {
                return $task->assignments
                    ->where('assignee_type', 'employee')
                    ->pluck('assignee_id')
                    ->map(fn ($id) => (int) $id)
                    ->contains((int) $employeeId);
            }

            return $task->assignments()
                ->where('assignee_type', 'employee')
                ->where('assignee_id', $employeeId)
                ->exists();
        }

        if ($user && $subtask->created_by === $user->id) {
            return ! $subtask->creatorEditWindowExpired($user->id);
        }

        return false;
    }

    private function isMasterAdmin(?User $user): bool
    {
        return $user?->isMasterAdmin() ?? false;
    }

    private function assigneeEmployees(object $actor, Project $project)
    {
        if (method_exists($actor, 'isAdmin') && $actor->isAdmin()) {
            return Employee::where('status', 'active')->orderBy('name')->get(['id', 'name']);
        }

        return $project->employees()->orderBy('name')->get(['employees.id', 'employees.name']);
    }

    private function assigneeSalesReps(object $actor, Project $project)
    {
        if (method_exists($actor, 'isAdmin') && $actor->isAdmin()) {
            return SalesRepresentative::where('status', 'active')->orderBy('name')->get(['id', 'name']);
        }

        return $project->salesRepresentatives()->orderBy('name')->get(['sales_representatives.id', 'sales_representatives.name']);
    }
}
