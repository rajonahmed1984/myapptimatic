<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskSubtask;
use App\Models\ProjectTaskSubtaskComment;
use App\Services\SubtaskCommentNotificationService;
use App\Support\DateTimeFormat;
use App\Support\PublicStorageUrl;
use App\Support\TaskActivityLogger;
use App\Support\TaskSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

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

        $maxMb = TaskSettings::uploadMaxMb();
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer'],
            'image' => ['sometimes', 'nullable', 'image', 'max:' . ($maxMb * 1024)],
            'images' => ['sometimes', 'nullable', 'array'],
            'images.*' => ['image', 'max:' . ($maxMb * 1024)],
        ]);

        $rootParentId = null;
        if (! empty($data['parent_id'])) {
            $parent = ProjectTaskSubtaskComment::query()
                ->where('project_task_subtask_id', $subtask->id)
                ->findOrFail((int) $data['parent_id']);

            $rootParentId = $parent->parent_id ?: $parent->id;
        }

        $paths = [];
        if ($request->hasFile('images')) {
            foreach ((array) $request->file('images') as $file) {
                if ($file && $file->isValid()) {
                    $paths[] = $this->storeAttachment($file, $task);
                }
            }
        }
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            if ($file && $file->isValid()) {
                $paths[] = $this->storeAttachment($file, $task);
            }
        }
        $paths = array_values(array_unique($paths));

        $actorIdentity = TaskActivityLogger::resolveActorIdentity($request);
        $commentData = [
            'project_task_id' => $task->id,
            'project_task_subtask_id' => $subtask->id,
            'parent_id' => $rootParentId,
            'actor_type' => (string) ($actorIdentity['type'] ?? 'client'),
            'actor_id' => (int) ($actorIdentity['id'] ?? 0),
            'message' => trim((string) $data['message']),
        ];

        if (! empty($paths)) {
            $commentData['attachment_paths'] = $paths;
            $commentData['attachment_path'] = $paths[0];
        }

        $comment = ProjectTaskSubtaskComment::create($commentData);

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

    private function storeAttachment(UploadedFile $file, ProjectTask $task): string
    {
        $hash = Str::random(12);
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $fileName = time() . '_' . $hash . '.' . $ext;

        return $file->storeAs("tasks/{$task->id}/subtasks/comments", $fileName, 'public');
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
            'attachments' => collect($comment->allAttachmentUrls())->map(function (string $path) {
                $name = pathinfo($path, PATHINFO_BASENAME);
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true);

                return [
                    'path' => $path,
                    'name' => $name,
                    'url' => PublicStorageUrl::fromPath($path),
                    'is_image' => $isImage,
                ];
            })->all(),
        ];
    }
}
