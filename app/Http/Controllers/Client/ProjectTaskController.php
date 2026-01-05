<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Support\SystemLogger;
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
        ]);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => 'pending',
            'start_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'assigned_type' => null,
            'assigned_id' => null,
            'customer_visible' => true,
            'progress' => 0,
            'created_by' => $request->user()->id,
        ]);

        SystemLogger::write('activity', 'Project task created (client).', [
            'project_id' => $project->id,
            'task_id' => $task->id,
            'actor_type' => 'client',
            'actor_id' => $request->user()->id,
        ]);

        return back()->with('status', 'Task added.');
    }

    public function update(Request $request, Project $project, ProjectTask $task): RedirectResponse
    {
        $this->authorize('update', $task);
        $this->ensureTaskBelongsToProject($project, $task);

        $data = $request->validate([
            'status' => ['required', 'in:pending,in_progress,blocked,completed,done'],
            'description' => ['nullable', 'string'],
        ]);

        $payload = [
            'status' => $data['status'],
            'description' => $data['description'] ?? $task->description,
        ];

        $task->update($payload);

        SystemLogger::write('activity', 'Project task updated (client).', [
            'project_id' => $project->id,
            'task_id' => $task->id,
            'actor_type' => 'client',
            'actor_id' => $request->user()->id,
        ]);

        return back()->with('status', 'Task updated.');
    }

    private function ensureTaskBelongsToProject(Project $project, ProjectTask $task): void
    {
        if ($task->project_id !== $project->id) {
            abort(404);
        }
    }
}
