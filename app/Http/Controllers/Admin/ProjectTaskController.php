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

class ProjectTaskController extends Controller
{

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

        if ($request->expectsJson()) {
            $task->load(['assignments.employee', 'assignments.salesRep']);

            return response()->json([
                'ok' => true,
                'message' => 'Task added.',
                'data' => [
                    'task_id' => $task->id,
                    'row_html' => view('admin.projects.partials.task-row', [
                        'task' => $task,
                        'project' => $project,
                        'taskTypeOptions' => TaskSettings::taskTypeOptions(),
                    ])->render(),
                ],
            ]);
        }

        return back()->with('status', 'Task added.');
    }

    public function update(UpdateTaskRequest $request, Project $project, ProjectTask $task): RedirectResponse|JsonResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $this->authorize('update', $task);

        if ($request->hasAny(['start_date', 'due_date'])) {
            return back()->withErrors(['dates' => 'Task dates cannot be changed after creation.']);
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

        if ($request->expectsJson()) {
            $task->load(['assignments.employee', 'assignments.salesRep']);

            return response()->json([
                'ok' => true,
                'message' => 'Task updated.',
                'data' => [
                    'task_id' => $task->id,
                    'row_html' => view('admin.projects.partials.task-row', [
                        'task' => $task,
                        'project' => $project,
                        'taskTypeOptions' => TaskSettings::taskTypeOptions(),
                    ])->render(),
                ],
            ]);
        }

        return back()->with('status', 'Task updated.');
    }

    public function updateAssignees(Request $request, Project $project, ProjectTask $task): JsonResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $this->authorize('update', $task);

        if (! $request->user()?->isMasterAdmin() && $task->creatorEditWindowExpired($request->user()?->id)) {
            return response()->json([
                'ok' => false,
                'message' => 'You can only edit this task within 24 hours of creation.',
            ], 403);
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

        if ($request->expectsJson()) {
            $task->load(['assignments.employee', 'assignments.salesRep']);

            return response()->json([
                'ok' => true,
                'message' => 'Task status updated.',
                'data' => [
                    'task_id' => $task->id,
                    'row_html' => view('admin.projects.partials.task-row', [
                        'task' => $task,
                        'project' => $project,
                        'taskTypeOptions' => TaskSettings::taskTypeOptions(),
                    ])->render(),
                ],
            ]);
        }

        return back()->with('status', 'Task status updated.');
    }

    public function destroy(Project $project, ProjectTask $task): RedirectResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $this->authorize('delete', $task);

        if (! request()->user()?->isMasterAdmin() && $task->status === 'completed') {
            return back()->withErrors(['task' => 'Completed tasks cannot be deleted.']);
        }

        $task->delete();

        SystemLogger::write('activity', 'Project task deleted.', [
            'project_id' => $project->id,
            'task_id' => $task->id,
            'actor_type' => 'admin',
            'actor_id' => request()->user()?->id,
        ], request()->user()?->id, request()->ip());

        return back()->with('status', 'Task removed.');
    }

    private function validationError(Request $request, array $errors): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            $message = collect($errors)->flatten()->first() ?? 'Validation failed.';
            return response()->json([
                'ok' => false,
                'message' => $message,
                'errors' => $errors,
            ], 422);
        }

        return back()->withErrors($errors)->withInput();
    }

    private function forbiddenResponse(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'message' => $message,
            ], 403);
        }

        return back()->withErrors(['task' => $message]);
    }

    private function ensureTaskBelongsToProject(Project $project, ProjectTask $task): void
    {
        if ($task->project_id !== $project->id) {
            abort(404);
        }
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
