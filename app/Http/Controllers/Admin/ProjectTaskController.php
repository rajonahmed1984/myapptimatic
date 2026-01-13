<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Employee;
use App\Models\SalesRepresentative;
use App\Support\SystemLogger;
use App\Support\TaskActivityLogger;
use App\Support\TaskAssignmentManager;
use App\Support\TaskAssignees;
use App\Support\TaskSettings;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProjectTaskController extends Controller
{
    private const STATUSES = ['pending', 'in_progress', 'blocked', 'completed', 'done'];

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('createTask', $project);

        $taskTypeOptions = array_keys(TaskSettings::taskTypeOptions());
        $priorityOptions = array_keys(TaskSettings::priorityOptions());
        $maxMb = TaskSettings::uploadMaxMb();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'descriptions' => ['nullable', 'array'],
            'descriptions.*' => ['nullable', 'string'],
            'task_type' => ['required', Rule::in($taskTypeOptions)],
            'priority' => ['nullable', Rule::in($priorityOptions)],
            'time_estimate_minutes' => ['nullable', 'integer', 'min:0'],
            'tags' => ['nullable', 'string'],
            'relationship_ids' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:start_date'],
            'assignees' => ['nullable', 'array'],
            'assignees.*' => ['nullable', 'string'],
            'assignee' => ['nullable', 'string'], // format type:id (legacy)
            'customer_visible' => ['nullable', 'boolean'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf,docx,xlsx', 'max:' . ($maxMb * 1024)],
        ]);

        $assigneeInputs = $data['assignees'] ?? [];
        if (empty($assigneeInputs) && ! empty($data['assignee'])) {
            $assigneeInputs = [$data['assignee']];
        }

        $assignees = TaskAssignees::parse($assigneeInputs);
        if (empty($assignees)) {
            return back()->withErrors(['assignees' => 'Select at least one assignee.'])->withInput();
        }

        $description = $this->combineDescriptions($data);
        $taskType = $data['task_type'];

        if ($taskType === 'upload' && ! $request->hasFile('attachment')) {
            return back()->withErrors(['attachment' => 'Upload tasks require at least one file.'])->withInput();
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

        return back()->with('status', 'Task added.');
    }

    public function update(Request $request, Project $project, ProjectTask $task): RedirectResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $this->authorize('update', $task);

        if ($request->hasAny(['start_date', 'due_date'])) {
            return back()->withErrors(['dates' => 'Task dates cannot be changed after creation.']);
        }

        $taskTypeOptions = array_keys(TaskSettings::taskTypeOptions());
        $priorityOptions = array_keys(TaskSettings::priorityOptions());

        $data = $request->validate([
            'status' => ['required', 'in:' . implode(',', self::STATUSES)],
            'description' => ['nullable', 'string'],
            'task_type' => ['nullable', Rule::in($taskTypeOptions)],
            'priority' => ['nullable', Rule::in($priorityOptions)],
            'time_estimate_minutes' => ['nullable', 'integer', 'min:0'],
            'tags' => ['nullable', 'string'],
            'relationship_ids' => ['nullable', 'string'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'customer_visible' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'assignees' => ['nullable', 'array'],
            'assignees.*' => ['nullable', 'string'],
        ]);

        $taskType = $data['task_type'] ?? $task->task_type ?? 'feature';
        if ($taskType === 'upload' && ! $task->activities()->where('type', 'upload')->exists()) {
            return back()->withErrors(['task_type' => 'Upload tasks require at least one file.'])->withInput();
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

        SystemLogger::write('activity', 'Project task updated.', [
            'project_id' => $project->id,
            'task_id' => $task->id,
            'status' => $task->status,
            'actor_type' => 'admin',
            'actor_id' => $request->user()?->id,
        ], $request->user()?->id, $request->ip());

        return back()->with('status', 'Task updated.');
    }

    public function changeStatus(Request $request, Project $project, ProjectTask $task): RedirectResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $this->authorize('update', $task);

        $data = $request->validate([
            'status' => ['required', 'in:' . implode(',', self::STATUSES)],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

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

        return back()->with('status', 'Task status updated.');
    }

    public function destroy(Project $project, ProjectTask $task): RedirectResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $this->authorize('delete', $task);

        if ($task->status === 'completed') {
            return back()->withErrors(['task' => 'Completed tasks cannot be deleted.']);
        }

        $task->delete();

        SystemLogger::write('activity', 'Project task deleted.', [
            'project_id' => $project->id,
            'task_id' => $task->id,
            'actor_type' => 'admin',
            'actor_id' => $request->user()?->id,
        ], $request->user()?->id, $request->ip());

        return back()->with('status', 'Task removed.');
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
