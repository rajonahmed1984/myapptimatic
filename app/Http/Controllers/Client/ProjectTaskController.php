<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Support\SystemLogger;
use App\Support\TaskActivityLogger;
use App\Support\TaskCompletionManager;
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
            'start_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'assigned_type' => null,
            'assigned_id' => null,
            'customer_visible' => true,
            'progress' => 0,
            'created_by' => $request->user()->id,
        ]);

        TaskActivityLogger::record($task, $request, 'system', 'Task created.');

        if ($request->hasFile('attachment')) {
            $path = $this->storeAttachment($request, $task);
            TaskActivityLogger::record($task, $request, 'upload', null, [], $path);
        }

        SystemLogger::write('activity', 'Project task created (client).', [
            'project_id' => $project->id,
            'task_id' => $task->id,
            'actor_type' => 'client',
            'actor_id' => $request->user()->id,
        ]);

        return back()->with('status', 'Task added.');
    }

    public function update(UpdateTaskRequest $request, Project $project, ProjectTask $task): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $task);
        $this->ensureTaskBelongsToProject($project, $task);

        if ($task->creatorEditWindowExpired($request->user()?->id)) {
            return $this->forbiddenResponse($request, 'Task can only be edited within 24 hours of creation.');
        }

        $data = $request->validated();

        if (in_array($data['status'], ['completed', 'done'], true)
            && TaskCompletionManager::hasSubtasks($task)
            && ! TaskCompletionManager::allSubtasksCompleted($task)) {
            return back()->withErrors(['status' => 'Complete all subtasks before completing this task.']);
        }

        $payload = [
            'status' => $data['status'],
            'description' => $data['description'] ?? $task->description,
        ];

        $previousStatus = $task->status;

        $isCompleted = in_array($data['status'], ['completed', 'done'], true);
        if ($task->status !== 'completed' && $isCompleted) {
            $payload['completed_at'] = Carbon::now();
        }

        $task->update($payload);

        if ($previousStatus !== $task->status) {
            TaskActivityLogger::record($task, $request, 'status', 'Status changed to ' . ucfirst(str_replace('_', ' ', $task->status)) . '.');
        } else {
            TaskActivityLogger::record($task, $request, 'system', 'Task updated.');
        }

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
