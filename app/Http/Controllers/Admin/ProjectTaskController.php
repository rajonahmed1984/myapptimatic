<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Employee;
use App\Models\SalesRepresentative;
use App\Support\SystemLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectTaskController extends Controller
{
    private const STATUSES = ['pending', 'in_progress', 'blocked', 'completed', 'done'];

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('createTask', $project);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:start_date'],
            'assignee' => ['required', 'string'], // format type:id
            'customer_visible' => ['nullable', 'boolean'],
        ]);

        [$type, $id] = $this->parseAssignee($data['assignee']);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => 'pending',
            'start_date' => $data['start_date'],
            'due_date' => $data['due_date'],
            'assigned_type' => $type,
            'assigned_id' => $id,
            'customer_visible' => (bool) ($data['customer_visible'] ?? false),
            'progress' => 0,
            'created_by' => $request->user()?->id,
        ]);

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

        $data = $request->validate([
            'status' => ['required', 'in:' . implode(',', self::STATUSES)],
            'description' => ['nullable', 'string'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'customer_visible' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $payload = [
            'description' => $data['description'] ?? $task->description,
            'status' => $data['status'],
            'progress' => $data['progress'] ?? $task->progress,
            'customer_visible' => (bool) ($data['customer_visible'] ?? $task->customer_visible),
            'notes' => $data['notes'] ?? $task->notes,
        ];

        $isCompleted = in_array($data['status'], ['completed', 'done'], true);
        if ($task->status !== 'completed' && $isCompleted) {
            $payload['completed_at'] = Carbon::now();
        }

        $task->update($payload);

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

        $isCompleted = in_array($data['status'], ['completed', 'done'], true);
        if ($task->status !== 'completed' && $isCompleted) {
            $payload['completed_at'] = Carbon::now();
        }

        $task->update($payload);

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
}
