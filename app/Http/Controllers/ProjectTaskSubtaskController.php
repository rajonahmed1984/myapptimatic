<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskSubtask;
use App\Support\TaskCompletionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectTaskSubtaskController extends Controller
{
    public function store(Request $request, Project $project, ProjectTask $task)
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('create', [ProjectTaskSubtask::class, $task]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'due_time' => ['sometimes', 'nullable', 'date_format:H:i'],
        ]);

        $data['created_by'] = $request->user()?->id;
        $subtask = $task->subtasks()->create($data);
        TaskCompletionManager::syncFromSubtasks($task);

        if ($request->wantsJson()) {
            return response()->json(['id' => $subtask->id, 'message' => 'Subtask added.'], 201);
        }

        return back()->with('status', 'Subtask added.');
    }

    public function update(Request $request, Project $project, ProjectTask $task, ProjectTaskSubtask $subtask)
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $this->ensureSubtaskBelongsToTask($task, $subtask);
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('update', $subtask);

        if (! $this->isMasterAdmin($request->user()) && $subtask->creatorEditWindowExpired($request->user()?->id)) {
            return $this->forbiddenResponse($request, 'You can only edit this subtask within 24 hours of creation.');
        }

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'due_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'is_completed' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('is_completed', $data)) {
            if ($data['is_completed'] && !$subtask->completed_at) {
                $data['completed_at'] = now();
            } elseif (!$data['is_completed']) {
                $data['completed_at'] = null;
            }
        }

        $subtask->update($data);
        TaskCompletionManager::syncFromSubtasks($task);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Subtask updated.']);
        }

        return back()->with('status', 'Subtask updated.');
    }

    public function destroy(Request $request, Project $project, ProjectTask $task, ProjectTaskSubtask $subtask)
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $this->ensureSubtaskBelongsToTask($task, $subtask);
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('delete', $subtask);

        $subtask->delete();
        TaskCompletionManager::syncFromSubtasks($task);

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

    private function forbiddenResponse(Request $request, string $message): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'message' => $message,
            ], 403);
        }

        return back()->withErrors(['subtask' => $message]);
    }

    private function isMasterAdmin($user): bool
    {
        return $user instanceof \App\Models\User && $user->isMasterAdmin();
    }
}
