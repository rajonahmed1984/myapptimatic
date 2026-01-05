<?php

namespace App\Http\Controllers\SalesRep;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Support\SystemLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectTaskController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('createTask', $project);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:start_date'],
            'customer_visible' => ['nullable', 'boolean'],
        ]);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => 'pending',
            'start_date' => $data['start_date'],
            'due_date' => $data['due_date'],
            'assigned_type' => 'sales_rep',
            'assigned_id' => $this->repId($request),
            'customer_visible' => (bool) ($data['customer_visible'] ?? false),
            'progress' => 0,
            'created_by' => $request->user()->id,
        ]);

        SystemLogger::write('activity', 'Project task created (sales rep).', [
            'project_id' => $project->id,
            'task_id' => $task->id,
            'actor_type' => 'sales_rep',
            'actor_id' => $request->user()->id,
        ]);

        return back()->with('status', 'Task added.');
    }

    public function update(Request $request, Project $project, ProjectTask $task): RedirectResponse
    {
        $this->authorize('update', $task);
        $this->ensureTaskBelongsToProject($project, $task);

        if ($request->hasAny(['start_date', 'due_date'])) {
            return back()->withErrors(['dates' => 'Task dates cannot be changed after creation.']);
        }

        $data = $request->validate([
            'status' => ['required', 'in:pending,in_progress,blocked,completed,done'],
            'description' => ['nullable', 'string'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'customer_visible' => ['nullable', 'boolean'],
        ]);

        $payload = [
            'status' => $data['status'],
            'description' => $data['description'] ?? $task->description,
            'progress' => $data['progress'] ?? $task->progress,
            'customer_visible' => (bool) ($data['customer_visible'] ?? $task->customer_visible),
        ];

        $isCompleted = in_array($data['status'], ['completed', 'done'], true);
        if ($task->status !== 'completed' && $isCompleted) {
            $payload['completed_at'] = Carbon::now();
        }

        $task->update($payload);

        SystemLogger::write('activity', 'Project task updated (sales rep).', [
            'project_id' => $project->id,
            'task_id' => $task->id,
            'actor_type' => 'sales_rep',
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

        SystemLogger::write('activity', 'Project task deleted (sales rep).', [
            'project_id' => $project->id,
            'task_id' => $task->id,
            'actor_type' => 'sales_rep',
            'actor_id' => $request->user()->id,
        ]);

        return back()->with('status', 'Task removed.');
    }

    private function ensureTaskBelongsToProject(Project $project, ProjectTask $task): void
    {
        if ($task->project_id !== $project->id) {
            abort(404);
        }
    }

    private function repId(Request $request): int
    {
        $repId = \App\Models\SalesRepresentative::where('user_id', $request->user()->id)->value('id');
        if (! $repId) {
            abort(403, 'Sales representative not found for user.');
        }
        return $repId;
    }
}
