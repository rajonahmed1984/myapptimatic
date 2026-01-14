<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskSubtask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectTaskSubtaskController extends Controller
{
    public function store(Request $request, Project $project, ProjectTask $task)
    {
        $this->ensureTaskBelongsToProject($project, $task);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'due_time' => ['nullable', 'date_format:H:i'],
        ]);

        $subtask = $task->subtasks()->create($data);

        if ($request->wantsJson()) {
            return response()->json(['id' => $subtask->id, 'message' => 'Subtask added.'], 201);
        }

        return back()->with('status', 'Subtask added.');
    }

    public function update(Request $request, Project $project, ProjectTask $task, ProjectTaskSubtask $subtask)
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $this->ensureSubtaskBelongsToTask($task, $subtask);

        $data = $request->validate([
            'is_completed' => ['required', 'boolean'],
        ]);

        if ($data['is_completed'] && !$subtask->completed_at) {
            $data['completed_at'] = now();
        } elseif (!$data['is_completed']) {
            $data['completed_at'] = null;
        }

        $subtask->update($data);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Subtask updated.']);
        }

        return back()->with('status', 'Subtask updated.');
    }

    public function destroy(Project $project, ProjectTask $task, ProjectTaskSubtask $subtask)
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $this->ensureSubtaskBelongsToTask($task, $subtask);

        $subtask->delete();

        return back()->with('status', 'Subtask deleted.');
    }

    private function ensureTaskBelongsToProject(Project $project, ProjectTask $task): void
    {
        if ($task->project_id !== $project->id) {
            abort(404);
        }
    }

    private function ensureSubtaskBelongsToTask(ProjectTask $task, ProjectTaskSubtask $subtask): void
    {
        if ($subtask->project_task_id !== $task->id) {
            abort(404);
        }
    }
}
