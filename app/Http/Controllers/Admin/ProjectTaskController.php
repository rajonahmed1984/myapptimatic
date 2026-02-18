<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Requests\UpdateTaskStatusRequest;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Employee;
use App\Models\SalesRepresentative;
use App\Support\AjaxResponse;
use App\Support\SystemLogger;
use App\Support\TaskActivityLogger;
use App\Support\TaskAssignmentManager;
use App\Support\TaskAssignees;
use App\Support\TaskCompletionManager;
use App\Support\TaskSettings;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProjectTaskController extends Controller
{
    public function create(Request $request, Project $project): View
    {
        $this->authorize('createTask', $project);

        if (AjaxResponse::ajaxFromRequest($request)) {
            return view('admin.projects.partials.task-form', $this->taskFormData($project));
        }

        return view('admin.projects.task-form-page', array_merge(
            $this->taskFormData($project),
            ['ajaxForm' => false]
        ));
    }

    public function edit(Request $request, Project $project, ProjectTask $task): View
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $this->authorize('update', $task);

        if (AjaxResponse::ajaxFromRequest($request)) {
            return view('admin.projects.partials.task-form', $this->taskFormData($project, $task));
        }

        return view('admin.projects.task-form-page', array_merge(
            $this->taskFormData($project, $task),
            ['ajaxForm' => false]
        ));
    }

    public function store(StoreTaskRequest $request, Project $project): RedirectResponse|JsonResponse
    {
        $this->authorize('createTask', $project);

        $data = $request->validated();

        $assigneeInputs = $data['assignees'] ?? [];
        if (empty($assigneeInputs) && ! empty($data['assignee'])) {
            $assigneeInputs = [$data['assignee']];
        }

        $assignees = TaskAssignees::parse($assigneeInputs);
        if (empty($assignees)) {
            return $this->validationError($request, ['assignees' => 'Select at least one assignee.']);
        }

        $description = $this->combineDescriptions($data);
        $taskType = $data['task_type'];

        if ($taskType === 'upload' && ! $request->hasFile('attachment')) {
            return $this->validationError($request, ['attachment' => 'Upload tasks require at least one file.']);
        }

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => $data['title'],
            'description' => $description,
            'task_type' => $taskType,
            'status' => 'pending',
            'priority' => $data['priority'] ?? 'medium',
            'start_date' => $data['start_date'],
            'due_date' => $data['due_date'],
            'assigned_type' => $assignees[0]['type'] ?? null,
            'assigned_id' => $assignees[0]['id'] ?? null,
            'customer_visible' => (bool) ($data['customer_visible'] ?? TaskSettings::defaultCustomerVisible()),
            'progress' => 0,
            'created_by' => $request->user()?->id,
            'time_estimate_minutes' => $data['time_estimate_minutes'] ?? null,
            'tags' => $this->parseTags($data['tags'] ?? null),
            'relationship_ids' => $this->parseRelationships($data['relationship_ids'] ?? null),
        ]);

        TaskAssignmentManager::sync($task, $assignees);

        TaskActivityLogger::record($task, $request, 'system', 'Task created.');

        if ($request->hasFile('attachment')) {
            $path = $this->storeAttachment($request, $task);
            TaskActivityLogger::record($task, $request, 'upload', null, [], $path);
        }

        SystemLogger::write('activity', 'Project task created.', [
            'project_id' => $project->id,
            'task_id' => $task->id,
            'status' => $task->status,
            'actor_type' => 'admin',
            'actor_id' => $request->user()?->id,
        ], $request->user()?->id, $request->ip());

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxOk(
                'Task added.',
                $this->taskPatches($request, $project),
                closeModal: true
            );
        }

        if ($request->filled('return_to')) {
            return redirect()->to((string) $request->input('return_to'))->with('status', 'Task added.');
        }

        return back()->with('status', 'Task added.');
    }

    public function update(UpdateTaskRequest $request, Project $project, ProjectTask $task): RedirectResponse|JsonResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $this->authorize('update', $task);

        if ($request->hasAny(['start_date', 'due_date'])) {
            return $this->validationError($request, ['dates' => 'Task dates cannot be changed after creation.']);
        }

        if (! $request->user()?->isMasterAdmin() && $task->creatorEditWindowExpired($request->user()?->id)) {
            return $this->forbiddenResponse($request, 'You can only edit this task within 24 hours of creation.');
        }

        $data = $request->validated();

        if (! $request->user()?->isMasterAdmin()
            && in_array($data['status'], ['completed', 'done'], true)
            && TaskCompletionManager::hasSubtasks($task)
            && ! TaskCompletionManager::allSubtasksCompleted($task)) {
            return $this->validationError($request, [
                'status' => 'Complete all subtasks before completing this task.',
            ]);
        }

        $taskType = $data['task_type'] ?? $task->task_type ?? 'feature';
        if ($taskType === 'upload' && ! $task->activities()->where('type', 'upload')->exists()) {
            return $this->validationError($request, ['task_type' => 'Upload tasks require at least one file.']);
        }

        $payload = [
            'description' => $data['description'] ?? $task->description,
            'task_type' => $taskType,
            'status' => $data['status'],
            'priority' => $data['priority'] ?? $task->priority ?? 'medium',
            'time_estimate_minutes' => $data['time_estimate_minutes'] ?? $task->time_estimate_minutes,
            'tags' => $this->parseTags($data['tags'] ?? null, $task->tags ?? []),
            'relationship_ids' => $this->parseRelationships($data['relationship_ids'] ?? null, $task->relationship_ids ?? []),
            'progress' => $data['progress'] ?? $task->progress,
            'customer_visible' => (bool) ($data['customer_visible'] ?? $task->customer_visible),
            'notes' => $data['notes'] ?? $task->notes,
        ];

        $previousStatus = $task->status;

        $isCompleted = in_array($data['status'], ['completed', 'done'], true);
        if ($task->status !== 'completed' && $isCompleted) {
            $payload['completed_at'] = Carbon::now();
        }

        $task->update($payload);

        if ($previousStatus !== $task->status) {
            TaskActivityLogger::record($task, $request, 'status', 'Status changed to ' . ucfirst(str_replace('_', ' ', $task->status)) . '.');
        }

        if (! empty($data['assignees'])) {
            $assignees = TaskAssignees::parse($data['assignees']);
            if (empty($assignees)) {
                return $this->validationError($request, ['assignees' => 'Select at least one valid assignee.']);
            }
            $changes = TaskAssignmentManager::sync($task, $assignees);
            if ($changes['before'] !== $changes['after']) {
                TaskActivityLogger::record($task, $request, 'assignment', 'Assignees updated.');
            }
        }

        if ($previousStatus === $task->status && empty($data['assignees'])) {
            TaskActivityLogger::record($task, $request, 'system', 'Task updated.');
        }

        SystemLogger::write('activity', 'Project task updated.', [
            'project_id' => $project->id,
            'task_id' => $task->id,
            'status' => $task->status,
            'actor_type' => 'admin',
            'actor_id' => $request->user()?->id,
        ], $request->user()?->id, $request->ip());

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxOk(
                'Task updated.',
                $this->taskPatches($request, $project),
                closeModal: true
            );
        }

        if ($request->filled('return_to')) {
            return redirect()->to((string) $request->input('return_to'))->with('status', 'Task updated.');
        }

        return back()->with('status', 'Task updated.');
    }

    public function updateAssignees(Request $request, Project $project, ProjectTask $task): JsonResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $this->authorize('update', $task);

        if (! $request->user()?->isMasterAdmin() && $task->creatorEditWindowExpired($request->user()?->id)) {
            return AjaxResponse::ajaxError('You can only edit this task within 24 hours of creation.', 403);
        }

        $data = $request->validate([
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
        ]);

        $employeeIds = array_values(array_unique($data['employee_ids'] ?? []));
        $employeeAssignees = array_map(fn ($id) => ['type' => 'employee', 'id' => $id], $employeeIds);

        $otherAssignees = $task->assignments()
            ->where('assignee_type', '!=', 'employee')
            ->get(['assignee_type', 'assignee_id'])
            ->map(fn ($assignment) => ['type' => $assignment->assignee_type, 'id' => $assignment->assignee_id])
            ->all();

        $assignees = array_values(array_merge($employeeAssignees, $otherAssignees));

        if (empty($assignees)) {
            $task->assignments()->delete();
            $task->assigned_type = null;
            $task->assigned_id = null;
            $task->save();
        } else {
            TaskAssignmentManager::sync($task, $assignees);
        }

        $task->load(['assignments.employee', 'assignments.salesRep']);

        $payload = $task->assignments
            ->map(fn ($assignment) => [
                'type' => $assignment->assignee_type,
                'id' => $assignment->assignee_id,
                'name' => $assignment->assigneeName(),
            ])
            ->values();

        return response()->json([
            'ok' => true,
            'assignees' => $payload,
        ]);
    }

    public function changeStatus(UpdateTaskStatusRequest $request, Project $project, ProjectTask $task): RedirectResponse|JsonResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $this->authorize('update', $task);

        if (! $request->user()?->isMasterAdmin() && $task->creatorEditWindowExpired($request->user()?->id)) {
            return $this->forbiddenResponse($request, 'You can only edit this task within 24 hours of creation.');
        }

        $data = $request->validated();

        if (! $request->user()?->isMasterAdmin()
            && in_array($data['status'], ['completed', 'done'], true)
            && TaskCompletionManager::hasSubtasks($task)
            && ! TaskCompletionManager::allSubtasksCompleted($task)) {
            return $this->validationError($request, [
                'status' => 'Complete all subtasks before completing this task.',
            ]);
        }

        $payload = [
            'status' => $data['status'],
            'progress' => $data['progress'] ?? $task->progress,
        ];

        $previousStatus = $task->status;

        $isCompleted = in_array($data['status'], ['completed', 'done'], true);
        if ($task->status !== 'completed' && $isCompleted) {
            $payload['completed_at'] = Carbon::now();
        }

        $task->update($payload);

        if ($previousStatus !== $task->status) {
            TaskActivityLogger::record($task, $request, 'status', 'Status changed to ' . ucfirst(str_replace('_', ' ', $task->status)) . '.');
        }

        SystemLogger::write('activity', 'Project task status updated.', [
            'project_id' => $project->id,
            'task_id' => $task->id,
            'status' => $task->status,
            'actor_type' => 'admin',
            'actor_id' => $request->user()?->id,
        ], $request->user()?->id, $request->ip());

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxOk(
                'Task status updated.',
                $this->taskPatches($request, $project),
                closeModal: false
            );
        }

        return back()->with('status', 'Task status updated.');
    }

    public function destroy(Request $request, Project $project, ProjectTask $task): RedirectResponse|JsonResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $this->authorize('delete', $task);

        if (! $request->user()?->isMasterAdmin() && $task->status === 'completed') {
            return $this->validationError($request, ['task' => 'Completed tasks cannot be deleted.']);
        }

        $task->delete();

        SystemLogger::write('activity', 'Project task deleted.', [
            'project_id' => $project->id,
            'task_id' => $task->id,
            'actor_type' => 'admin',
            'actor_id' => $request->user()?->id,
        ], $request->user()?->id, $request->ip());

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxOk(
                'Task removed.',
                $this->taskPatches($request, $project),
                closeModal: false
            );
        }

        return back()->with('status', 'Task removed.');
    }

    private function validationError(Request $request, array $errors): RedirectResponse|JsonResponse
    {
        if (AjaxResponse::ajaxFromRequest($request)) {
            $message = collect($errors)->flatten()->first() ?? 'Validation failed.';
            return AjaxResponse::ajaxValidation($errors, null, $message);
        }

        return back()->withErrors($errors)->withInput();
    }

    private function forbiddenResponse(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxError($message, 403);
        }

        return back()->withErrors(['task' => $message]);
    }

    private function ensureTaskBelongsToProject(Project $project, ProjectTask $task): void
    {
        if ($task->project_id !== $project->id) {
            abort(404);
        }
    }

    private function taskFormData(Project $project, ?ProjectTask $task = null): array
    {
        return [
            'project' => $project,
            'task' => $task?->loadMissing(['assignments.employee', 'assignments.salesRep']),
            'taskTypeOptions' => TaskSettings::taskTypeOptions(),
            'priorityOptions' => TaskSettings::priorityOptions(),
            'employees' => Employee::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'designation']),
            'salesReps' => SalesRepresentative::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'statusOptions' => ['pending', 'in_progress', 'blocked', 'completed'],
        ];
    }

    private function taskPatches(Request $request, Project $project): array
    {
        $payload = $this->tasksPayload($request, $project);

        return [
            [
                'action' => 'replace',
                'selector' => '#tasksTableWrap',
                'html' => view('admin.projects.partials.tasks-table', $payload)->render(),
            ],
            [
                'action' => 'replace',
                'selector' => '#projectTaskStats',
                'html' => view('admin.projects.partials.tasks-stats', $payload)->render(),
            ],
        ];
    }

    private function tasksPayload(Request $request, Project $project): array
    {
        $user = $request->user();
        $statusFilter = (string) ($request->query('status') ?? $request->input('task_status_filter', ''));
        $statusFilter = in_array($statusFilter, ['pending', 'in_progress', 'blocked', 'completed'], true)
            ? $statusFilter
            : null;
        $tasks = $project->tasks()
            ->with(['assignments.employee', 'assignments.salesRep', 'creator'])
            ->when($user?->isClient(), fn ($query) => $query->where('customer_visible', true))
            ->when($statusFilter, function ($query, $status) {
                if ($status === 'completed') {
                    $query->whereIn('status', ['completed', 'done']);
                    return;
                }

                $query->where('status', $status);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25);

        if ($statusFilter) {
            $tasks->appends(['status' => $statusFilter]);
        } else {
            $tasks->withQueryString();
        }

        $baseSummary = $project->tasks()
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count")
            ->selectRaw("SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count")
            ->selectRaw("SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked_count")
            ->selectRaw("SUM(CASE WHEN status IN ('completed', 'done') THEN 1 ELSE 0 END) as completed_count")
            ->first();

        $summary = [
            'total' => (int) $project->tasks()->count(),
            'pending' => (int) ($baseSummary->pending_count ?? 0),
            'in_progress' => (int) ($baseSummary->in_progress_count ?? 0),
            'blocked' => (int) ($baseSummary->blocked_count ?? 0),
            'completed' => (int) ($baseSummary->completed_count ?? 0),
        ];

        return [
            'project' => $project,
            'tasks' => $tasks,
            'taskTypeOptions' => TaskSettings::taskTypeOptions(),
            'summary' => $summary,
            'statusFilter' => $statusFilter,
        ];
    }

    private function parseAssignee(string $value): array
    {
        [$type, $id] = array_pad(explode(':', $value, 2), 2, null);
        $id = $id ? (int) $id : null;

        if (! $type || ! $id) {
            abort(422, 'Invalid assignee');
        }

        if ($type === 'employee' && ! Employee::whereKey($id)->exists()) {
            abort(422, 'Employee not found');
        }

        if ($type === 'sales_rep' && ! SalesRepresentative::whereKey($id)->exists()) {
            abort(422, 'Sales representative not found');
        }

        return [$type, $id];
    }

    private function combineDescriptions(array $data): ?string
    {
        if (! empty($data['descriptions']) && is_array($data['descriptions'])) {
            $description = implode("\n", array_filter($data['descriptions']));
            return $description !== '' ? $description : null;
        }

        return $data['description'] ?? null;
    }

    private function parseTags(?string $tags, array $fallback = []): array
    {
        if ($tags === null) {
            return $fallback;
        }

        $parsed = array_filter(array_map('trim', explode(',', $tags)));
        return array_values(array_unique($parsed));
    }

    private function parseRelationships(?string $relationships, array $fallback = []): array
    {
        if ($relationships === null) {
            return $fallback;
        }

        $ids = array_filter(array_map('trim', explode(',', $relationships)));
        $ids = array_values(array_unique(array_filter($ids, fn ($id) => is_numeric($id))));
        return array_map('intval', $ids);
    }

    private function storeAttachment(Request $request, ProjectTask $task): ?string
    {
        if (! $request->hasFile('attachment')) {
            return null;
        }

        $file = $request->file('attachment');
        $name = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
        $name = $name !== '' ? Str::slug($name) : 'attachment';
        $extension = $file->getClientOriginalExtension();
        $fileName = $name . '-' . Str::random(8) . '.' . $extension;

        return $file->storeAs('project-task-activities/' . $task->id, $fileName, 'public');
    }
}
