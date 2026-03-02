<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskSubtask;
use App\Models\ProjectTaskSubtaskComment;
use App\Services\SubtaskCommentNotificationService;
use App\Support\DateTimeFormat;
use App\Support\TaskActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectTaskSubtaskCommentController extends Controller
{
    public function store(
        Request $request,
        Project $project,
        ProjectTask $task,
        ProjectTaskSubtask $subtask,
        SubtaskCommentNotificationService $notificationService
    ): RedirectResponse|JsonResponse {
        $this->ensureTaskBelongsToProject($project, $task);
        $this->ensureSubtaskBelongsToTask($task, $subtask);

        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('comment', $task);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $rootParentId = null;
        if (! empty($data['parent_id'])) {
            $parent = ProjectTaskSubtaskComment::query()
                ->where('project_task_subtask_id', $subtask->id)
                ->findOrFail((int) $data['parent_id']);

            $rootParentId = $parent->parent_id ?: $parent->id;
        }

        $actorIdentity = TaskActivityLogger::resolveActorIdentity($request);
        $comment = ProjectTaskSubtaskComment::create([
            'project_task_id' => $task->id,
            'project_task_subtask_id' => $subtask->id,
            'parent_id' => $rootParentId,
            'actor_type' => (string) ($actorIdentity['type'] ?? 'client'),
            'actor_id' => (int) ($actorIdentity['id'] ?? 0),
            'message' => trim((string) $data['message']),
        ]);

        $comment->load(['userActor', 'employeeActor', 'salesRepActor']);
        $notificationService->notify($task, $subtask, $comment);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Comment added.',
                'data' => [
                    'comment' => $this->commentItem($comment),
                ],
            ]);
        }

        return back()->with('status', 'Comment added.');
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

    private function commentItem(ProjectTaskSubtaskComment $comment): array
    {
        return [
            'id' => $comment->id,
            'parent_id' => $comment->parent_id,
            'message' => (string) $comment->message,
            'actor_name' => $comment->actorName(),
            'actor_type_label' => $comment->actorTypeLabel(),
            'created_at_display' => DateTimeFormat::formatDateTime($comment->created_at),
        ];
    }
}
