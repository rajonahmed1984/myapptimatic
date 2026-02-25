<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskSubtask;
use App\Models\SalesRepresentative;
use App\Models\User;
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

        $viewData = [
            'layout' => $this->layoutForPrefix($routePrefix),
            'routePrefix' => $routePrefix,
            'project' => $project,
            'task' => $task,
            'activities' => $activities,
            'activitiesPaginator' => $activityPaginator,
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
            'canEdit' => $canEditTask,
            'canStartTask' => $canStartTask,
            'canCompleteTask' => $canCompleteTask,
            'canAddSubtask' => $canAddSubtask,
            'editableSubtaskIds' => $editableSubtaskIds,
            'statusSubtaskIds' => $statusSubtaskIds,
            'canPost' => Gate::forUser($actor)->check('comment', $task),
            'updateRoute' => route($routePrefix . '.projects.tasks.update', [$project, $task]),
            'activityRoute' => route($routePrefix . '.projects.tasks.activity', [$project, $task]),
            'activityPostRoute' => route($routePrefix . '.projects.tasks.activity.store', [$project, $task]),
            'activityItemsUrl' => route($routePrefix . '.projects.tasks.activity.items', [$project, $task]),
            'activityItemsPostUrl' => route($routePrefix . '.projects.tasks.activity.items.store', [$project, $task]),
            'uploadRoute' => route($routePrefix . '.projects.tasks.upload', [$project, $task]),
            'backRoute' => $backRoute,
            'attachmentRouteName' => $attachmentRouteName,
            'uploadMaxMb' => TaskSettings::uploadMaxMb(),
        ];

        $legacyHtml = view('projects.task-detail-clickup', $viewData)->render();
        $payload = $this->extractLegacyPayload($legacyHtml);

        return Inertia::render('Projects/TaskDetailClickup', [
            'pageTitle' => (string) ($payload['page_title'] ?? ($task->title ?: 'Task Details')),
            'pageHeading' => (string) ($payload['page_heading'] ?? ($task->title ?: 'Task Details')),
            'pageKey' => $routePrefix . '.projects.tasks.show',
            'content_html' => (string) ($payload['content_html'] ?? ''),
            'script_html' => (string) ($payload['script_html'] ?? ''),
            'style_html' => (string) ($payload['style_html'] ?? ''),
        ]);
    }

    /**
     * @return array{page_title: string, page_heading: string, content_html: string, script_html: string, style_html: string}|null
     */
    private function extractLegacyPayload(string $html): ?array
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        if (! $loaded) {
            return null;
        }

        $contentNode = $dom->getElementById('appContent');
        if (! $contentNode) {
            return null;
        }

        $scriptsNode = $dom->getElementById('pageScriptStack');
        $styleHtml = '';

        foreach ($dom->getElementsByTagName('style') as $styleNode) {
            $styleHtml .= $dom->saveHTML($styleNode);
        }

        return [
            'page_title' => (string) $contentNode->getAttribute('data-page-title'),
            'page_heading' => (string) $contentNode->getAttribute('data-page-heading'),
            'content_html' => $this->innerHtml($contentNode),
            'script_html' => $scriptsNode ? $this->innerHtml($scriptsNode) : '',
            'style_html' => $styleHtml,
        ];
    }

    private function innerHtml(\DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument?->saveHTML($child) ?? '';
        }

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

    private function layoutForPrefix(string $prefix): string
    {
        return match ($prefix) {
            'client' => 'layouts.client',
            'rep' => 'layouts.rep',
            default => 'layouts.admin',
        };
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
