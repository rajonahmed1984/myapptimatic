<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\SalesRepresentative;
use App\Support\TaskSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectTaskViewController extends Controller
{
    public function show(Request $request, Project $project, ProjectTask $task)
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $task);

        $task->load([
            'assignments.employee',
            'assignments.salesRep',
            'activities.userActor',
            'activities.employeeActor',
            'activities.salesRepActor',
            'subtasks',
        ]);

        $activities = $task->activities->sortBy('created_at')->values();
        $uploadActivities = $activities->where('type', 'upload');

        $identity = $this->resolveActorIdentity($request);
        $routePrefix = $this->resolveRoutePrefix($request);
        $attachmentRouteName = $routePrefix . '.projects.tasks.activity.attachment';

        $assignees = $task->assignments
            ->map(fn ($assignment) => $assignment->assignee_type . ':' . $assignment->assignee_id)
            ->values()
            ->all();

        if (empty($assignees) && $task->assigned_type && $task->assigned_id) {
            $assignees = [$task->assigned_type . ':' . $task->assigned_id];
        }

        $employees = $this->assigneeEmployees($actor, $project);
        $salesReps = $this->assigneeSalesReps($actor, $project);

        $taskTypeOptions = TaskSettings::taskTypeOptions();
        if ($task->task_type && ! array_key_exists($task->task_type, $taskTypeOptions)) {
            $taskTypeOptions[$task->task_type] = ucfirst(str_replace('_', ' ', $task->task_type));
        }

        return view('projects.task-detail-clickup', [
            'layout' => $this->layoutForPrefix($routePrefix),
            'routePrefix' => $routePrefix,
            'project' => $project,
            'task' => $task,
            'activities' => $activities,
            'uploadActivities' => $uploadActivities,
            'taskTypeOptions' => $taskTypeOptions,
            'priorityOptions' => TaskSettings::priorityOptions(),
            'statusColors' => [
                'pending' => ['bg' => '#f1f5f9', 'text' => '#64748b'],
                'in_progress' => ['bg' => '#fef3c7', 'text' => '#b45309'],
                'blocked' => ['bg' => '#fee2e2', 'text' => '#b91c1c'],
                'completed' => ['bg' => '#d1fae5', 'text' => '#065f46'],
            ],
            'priorityColors' => [
                'urgent' => ['color' => '#ef4444'],
                'high' => ['color' => '#f97316'],
                'medium' => ['color' => '#eab308'],
                'low' => ['color' => '#22c55e'],
            ],
            'assignees' => $assignees,
            'employees' => $employees,
            'salesReps' => $salesReps,
            'currentActorType' => $identity['type'],
            'currentActorId' => $identity['id'],
            'canEdit' => $this->canEdit($actor),
            'canPost' => Gate::forUser($actor)->check('comment', $task),
            'updateRoute' => route($routePrefix . '.projects.tasks.update', [$project, $task]),
            'activityRoute' => route($routePrefix . '.projects.tasks.activity', [$project, $task]),
            'activityPostRoute' => route($routePrefix . '.projects.tasks.activity.store', [$project, $task]),
            'uploadRoute' => route($routePrefix . '.projects.tasks.upload', [$project, $task]),
            'backRoute' => route($routePrefix . '.projects.show', $project),
            'attachmentRouteName' => $attachmentRouteName,
            'pollUrl' => route($routePrefix . '.projects.tasks.activity', [$project, $task], false) . '?partial=1',
            'uploadMaxMb' => TaskSettings::uploadMaxMb(),
        ]);
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

    private function layoutForPrefix(string $prefix): string
    {
        return match ($prefix) {
            'client' => 'layouts.client',
            'rep' => 'layouts.rep',
            default => 'layouts.admin',
        };
    }

    private function canEdit(object $actor): bool
    {
        if ($actor instanceof Employee) {
            return true;
        }

        if (method_exists($actor, 'isAdmin') && $actor->isAdmin()) {
            return true;
        }

        if (method_exists($actor, 'isSales') && $actor->isSales()) {
            return true;
        }

        return false;
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
