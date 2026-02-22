<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskSubtask;
use App\Models\User;
use App\Support\TaskCompletionManager;
use App\Support\TaskSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProjectTaskSubtaskController extends Controller
{
    public function store(Request $request, Project $project, ProjectTask $task)
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('create', [ProjectTaskSubtask::class, $task]);
        $maxMb = TaskSettings::uploadMaxMb();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'due_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'image' => ['sometimes', 'nullable', 'image', 'max:' . ($maxMb * 1024)],
        ]);

        unset($data['image']);

        $creatorId = null;
        $user = $request->user();
        if ($user instanceof User) {
            $creatorId = $user->id;
        } elseif ($user instanceof Employee) {
            $creatorId = $user->user_id;
        } elseif ($actor instanceof Employee) {
            $creatorId = $actor->user_id;
        }

        $data['created_by'] = $creatorId;
        if ($request->hasFile('image')) {
            $data['attachment_path'] = $this->storeAttachment($request->file('image'), $task);
        }

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

        $user = $request->user();
        $isAssigned = $this->isAssignedEmployee($task, $user);
        $isCreator = $user && $subtask->created_by && $subtask->created_by === $user->id;

        if (! $this->isMasterAdmin($user) && ! $isAssigned && $subtask->creatorEditWindowExpired($user?->id)) {
            return $this->forbiddenResponse($request, 'You can only edit this subtask within 24 hours of creation.');
        }

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'due_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'is_completed' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', 'in:open,in_progress,completed'],
        ]);

        if (! $this->isMasterAdmin($user) && $isAssigned && ! $isCreator) {
            $extraFields = array_diff(array_keys($data), ['is_completed', 'status']);
            if (! empty($extraFields)) {
                return $this->forbiddenResponse($request, 'Only subtask status changes are allowed.');
            }
        }

        if (array_key_exists('status', $data)) {
            if ($data['status'] === 'completed') {
                $data['is_completed'] = true;
                $data['completed_at'] = $subtask->completed_at ?: now();
            } else {
                $data['is_completed'] = false;
                $data['completed_at'] = null;
            }
        } elseif (array_key_exists('is_completed', $data)) {
            $data['status'] = $data['is_completed'] ? 'completed' : 'open';
        }

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

        if ($subtask->attachment_path) {
            Storage::disk('public')->delete($subtask->attachment_path);
        }

        $subtask->delete();
        TaskCompletionManager::syncFromSubtasks($task);

        return back()->with('status', 'Subtask deleted.');
    }

    public function attachment(Request $request, Project $project, ProjectTask $task, ProjectTaskSubtask $subtask)
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $this->ensureSubtaskBelongsToTask($task, $subtask);

        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $subtask);

        if (! $subtask->attachment_path) {
            abort(404, 'This subtask does not have an image attachment.');
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($subtask->attachment_path)) {
            abort(404, 'The image attachment is no longer available.');
        }

        if ($subtask->isImageAttachment()) {
            return $disk->response($subtask->attachment_path);
        }

        return $disk->download($subtask->attachment_path, $subtask->attachmentName() ?? 'subtask-attachment');
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

    private function isAssignedEmployee(ProjectTask $task, $user): bool
    {
        $employeeId = $user?->employee?->id;
        if (! $employeeId) {
            return false;
        }

        if ($task->assigned_type === 'employee' && (int) $task->assigned_id === (int) $employeeId) {
            return true;
        }

        if ($user && (int) $task->assignee_id === (int) $user->id) {
            return true;
        }

        return $task->assignments()
            ->where('assignee_type', 'employee')
            ->where('assignee_id', $employeeId)
            ->exists();
    }

    private function storeAttachment(UploadedFile $file, ProjectTask $task): string
    {
        $name = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
        $name = $name !== '' ? Str::slug($name) : 'subtask-image';
        $extension = $file->getClientOriginalExtension();
        $fileName = $name . '-' . Str::random(8) . '.' . $extension;

        return $file->storeAs('project-task-subtasks/' . $task->id, $fileName, 'public');
    }
}
