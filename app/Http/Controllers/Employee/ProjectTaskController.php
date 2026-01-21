<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Project;
use App\Models\ProjectTask;
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
    public function store(StoreTaskRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('createTask', $project);

        $data = $request->validated();

        $assignees = TaskAssignees::parse($data['assignees'] ?? []);
        if (empty($assignees)) {
            $assignees = [['type' => 'employee', 'id' => $request->user()->id]];
        }

        if ($data['task_type'] === 'upload' && ! $request->hasFile('attachment')) {
            return back()->withErrors(['attachment' => 'Upload tasks require at least one file.'])->withInput();
        }

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'task_type' => $data['task_type'],
            'status' => 'pending',
            'priority' => $data['priority'] ?? 'medium',
            'start_date' => $data['start_date'],
            'due_date' => $data['due_date'],
            'assigned_type' => $assignees[0]['type'] ?? 'employee',
            'assigned_id' => $assignees[0]['id'] ?? $request->user()->id,
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

        SystemLogger::write('activity', 'Project task created (employee).', [
            'project_id' => $project->id,
            'task_id' => $task->id,
            'actor_type' => 'employee',
            'actor_id' => $request->user()->id,
        ]);

        return back()->with('status', 'Task added.');
    }

    public function update(UpdateTaskRequest $request, Project $project, ProjectTask $task): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $task);
        $this->ensureTaskBelongsToProject($project, $task);

        if ($request->hasAny(['start_date', 'due_date'])) {
            return back()->withErrors(['dates' => 'Task dates cannot be changed after creation.']);
        }

        if (! $request->user()?->isMasterAdmin() && $task->creatorEditWindowExpired($request->user()?->id)) {
            return $this->forbiddenResponse($request, 'You can only edit this task within 24 hours of creation.');
        }

        $data = $request->validated();

        if (in_array($data['status'], ['completed', 'done'], true)
            && TaskCompletionManager::hasSubtasks($task)
            && ! TaskCompletionManager::allSubtasksCompleted($task)) {
            return back()->withErrors(['status' => 'Complete all subtasks before completing this task.']);
        }

        $taskType = $data['task_type'] ?? $task->task_type ?? 'feature';
        if ($taskType === 'upload' && ! $task->activities()->where('type', 'upload')->exists()) {
            return back()->withErrors(['task_type' => 'Upload tasks require at least one file.'])->withInput();
        }

        $payload = [
            'status' => $data['status'],
            'description' => $data['description'] ?? $task->description,
            'task_type' => $taskType,
            'priority' => $data['priority'] ?? $task->priority ?? 'medium',
            'time_estimate_minutes' => $data['time_estimate_minutes'] ?? $task->time_estimate_minutes,
            'tags' => $this->parseTags($data['tags'] ?? null, $task->tags ?? []),
            'relationship_ids' => $this->parseRelationships($data['relationship_ids'] ?? null, $task->relationship_ids ?? []),
            'progress' => $data['progress'] ?? $task->progress,
            'customer_visible' => (bool) ($data['customer_visible'] ?? $task->customer_visible),
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
                return back()->withErrors(['assignees' => 'Select at least one valid assignee.'])->withInput();
            }
            $changes = TaskAssignmentManager::sync($task, $assignees);
            if ($changes['before'] !== $changes['after']) {
                TaskActivityLogger::record($task, $request, 'assignment', 'Assignees updated.');
            }
        }

        if ($previousStatus === $task->status && empty($data['assignees'])) {
            TaskActivityLogger::record($task, $request, 'system', 'Task updated.');
        }

        SystemLogger::write('activity', 'Project task updated (employee).', [
            'project_id' => $project->id,
            'task_id' => $task->id,
            'actor_type' => 'employee',
            'actor_id' => $request->user()->id,
        ]);

        return back()->with('status', 'Task updated.');
    }

    public function destroy(Project $project, ProjectTask $task): RedirectResponse
    {
        $this->authorize('delete', $task);
        $this->ensureTaskBelongsToProject($project, $task);

        if ($task->status === 'completed') {
            return back()->withErrors(['task' => 'Completed tasks cannot be deleted.']);
        }

        $task->delete();

        SystemLogger::write('activity', 'Project task deleted (employee).', [
            'project_id' => $project->id,
            'task_id' => $task->id,
            'actor_type' => 'employee',
            'actor_id' => $request->user()->id,
        ]);

        return back()->with('status', 'Task removed.');
    }

    public function start(Request $request, Project $project, ProjectTask $task): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $task);
        $this->ensureTaskBelongsToProject($project, $task);

        if (! $request->user()?->isMasterAdmin() && $task->creatorEditWindowExpired($request->user()?->id)) {
            return $this->forbiddenResponse($request, 'You can only edit this task within 24 hours of creation.');
        }

        $currentStatus = $task->status ?? 'pending';
        if (in_array($currentStatus, ['completed', 'done'], true)) {
            return $this->startResponse($request, $task, 'Task already completed.');
        }

        if ($currentStatus === 'in_progress') {
            return $this->startResponse($request, $task, 'Task already in progress.');
        }

        if (! in_array($currentStatus, ['pending', 'todo'], true)) {
            return $this->startResponse($request, $task, 'Task status unchanged.');
        }

        $task->update(['status' => 'in_progress']);

        TaskActivityLogger::record($task, $request, 'status', 'Status changed to In progress.');
        SystemLogger::write('activity', 'Project task started (employee).', [
            'project_id' => $project->id,
            'task_id' => $task->id,
            'actor_type' => 'employee',
            'actor_id' => $request->user()->id,
        ]);

        return $this->startResponse($request, $task, 'Task marked as in progress.');
    }

    private function ensureTaskBelongsToProject(Project $project, ProjectTask $task): void
    {
        if ($task->project_id !== $project->id) {
            abort(404);
        }
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

    private function startResponse(Request $request, ProjectTask $task, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'data' => [
                    'task_id' => $task->id,
                    'status' => $task->status,
                ],
            ]);
        }

        return back()->with('status', $message);
    }
}
