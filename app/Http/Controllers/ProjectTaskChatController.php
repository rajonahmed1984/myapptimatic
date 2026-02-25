<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskMessage;
use App\Models\ProjectTaskMessageRead;
use App\Models\SalesRepresentative;
use App\Http\Requests\StoreTaskChatMessageRequest;
use App\Support\DateTimeFormat;
use App\Services\ChatAiService;
use App\Services\GeminiService;
use App\Services\ChatAiSummaryCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProjectTaskChatController extends Controller
{
    private const EDITABLE_WINDOW_SECONDS = 30;

    public function show(Request $request, Project $project, ProjectTask $task, ChatAiSummaryCache $summaryCache): InertiaResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $task);

        $routePrefix = $this->resolveRoutePrefix($request);
        $messages = $task->messages()
            ->with(['userAuthor', 'employeeAuthor', 'salesRepAuthor', 'replyToMessage'])
            ->latest('id')
            ->limit(30)
            ->get()
            ->reverse()
            ->values();

        $identity = $this->resolveAuthorIdentity($request);
        $attachmentRouteName = $routePrefix . '.projects.tasks.messages.attachment';
        $messageUpdateRouteName = $routePrefix . '.projects.tasks.chat.messages.update';
        $messageDeleteRouteName = $routePrefix . '.projects.tasks.chat.messages.destroy';
        $messagePinRouteName = $routePrefix . '.projects.tasks.chat.messages.pin';
        $messageReactionRouteName = $routePrefix . '.projects.tasks.chat.messages.react';

        $canPost = Gate::forUser($actor)->check('comment', $task);

        $initialItems = $messages->map(fn (ProjectTaskMessage $message) => $this->messageItem(
            $message,
            $project,
            $task,
            $attachmentRouteName,
            $identity,
            $messageUpdateRouteName,
            $messageDeleteRouteName,
            $messagePinRouteName,
            $messageReactionRouteName
        ))->values();

        return Inertia::render('Projects/TaskChat', [
            'pageTitle' => 'Task Chat',
            'pageHeading' => 'Task Chat',
            'pageKey' => $routePrefix . '.projects.tasks.chat',
            'routePrefix' => $routePrefix,
            'project' => [
                'id' => $project->id,
                'name' => (string) $project->name,
            ],
            'task' => [
                'id' => $task->id,
                'title' => (string) $task->title,
            ],
            'initialItems' => $initialItems->all(),
            'canPost' => $canPost,
            'aiReady' => (bool) config('google_ai.api_key'),
            'pinnedSummary' => $summaryCache->getTask($task->id) ?? [],
            'editableWindowSeconds' => self::EDITABLE_WINDOW_SECONDS,
            'routes' => [
                'back' => route($routePrefix . '.projects.tasks.show', [$project, $task]),
                'messages' => route($routePrefix . '.projects.tasks.chat.messages', [$project, $task]),
                'storeForm' => route($routePrefix . '.projects.tasks.chat.store', [$project, $task]),
                'storeMessage' => route($routePrefix . '.projects.tasks.chat.messages.store', [$project, $task]),
                'read' => route($routePrefix . '.projects.tasks.chat.read', [$project, $task]),
                'aiSummary' => route($routePrefix . '.projects.tasks.chat.ai', [$project, $task]),
            ],
        ]);
    }

    public function aiSummary(
        Request $request,
        Project $project,
        ProjectTask $task,
        ChatAiService $aiService,
        GeminiService $geminiService,
        ChatAiSummaryCache $summaryCache
    ): JsonResponse {
        $this->ensureTaskBelongsToProject($project, $task);
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $task);

        if (! config('google_ai.api_key')) {
            return response()->json(['error' => 'Missing GOOGLE_AI_API_KEY.'], 422);
        }

        try {
            $result = $aiService->analyzeTaskChat($project, $task, $geminiService);
            if (is_array($result['data'] ?? null)) {
                $result['data']['generated_at'] = now()->toDateTimeString();
                $summaryCache->putTask($task->id, $result['data']);
            }

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function messages(Request $request, Project $project, ProjectTask $task): JsonResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $task);

        $limit = min(max((int) $request->query('limit', 30), 1), 100);
        $afterId = (int) $request->query('after_id', 0);
        $beforeId = (int) $request->query('before_id', 0);

        $query = $task->messages()
            ->with(['userAuthor', 'employeeAuthor', 'salesRepAuthor', 'replyToMessage']);

        if ($afterId > 0) {
            $query->where('id', '>', $afterId)->orderBy('id');
        } elseif ($beforeId > 0) {
            $query->where('id', '<', $beforeId)->orderByDesc('id');
        } else {
            $query->orderByDesc('id');
        }

        $messages = $query->limit($limit)->get();
        if ($afterId <= 0) {
            $messages = $messages->reverse()->values();
        }

        $identity = $this->resolveAuthorIdentity($request);
        $routePrefix = $this->resolveRoutePrefix($request);
        $attachmentRouteName = $routePrefix . '.projects.tasks.messages.attachment';
        $messageUpdateRouteName = $routePrefix . '.projects.tasks.chat.messages.update';
        $messageDeleteRouteName = $routePrefix . '.projects.tasks.chat.messages.destroy';
        $messagePinRouteName = $routePrefix . '.projects.tasks.chat.messages.pin';
        $messageReactionRouteName = $routePrefix . '.projects.tasks.chat.messages.react';

        $items = $messages->map(fn (ProjectTaskMessage $message) => $this->messageItem(
            $message,
            $project,
            $task,
            $attachmentRouteName,
            $identity,
            $messageUpdateRouteName,
            $messageDeleteRouteName,
            $messagePinRouteName,
            $messageReactionRouteName
        ))->values();

        $nextAfterId = $messages->max('id') ?? $afterId;
        $nextBeforeId = $messages->min('id') ?? $beforeId;

        return response()->json([
            'ok' => true,
            'data' => [
                'items' => $items,
                'next_after_id' => $nextAfterId,
                'next_before_id' => $nextBeforeId,
            ],
        ]);
    }

    public function store(StoreTaskChatMessageRequest $request, Project $project, ProjectTask $task): RedirectResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('comment', $task);

        $data = $request->validated();
        $replyData = $request->validate([
            'reply_to_message_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $message = isset($data['message']) ? trim((string) $data['message']) : null;
        $message = $message === '' ? null : $message;
        $replyToMessageId = $this->resolveReplyTarget($task, (int) ($replyData['reply_to_message_id'] ?? 0));

        $attachmentPath = $this->storeAttachment($request, $task);
        if (! $attachmentPath && $message === null) {
            return back()->withErrors(['message' => 'Message cannot be empty.']);
        }

        $identity = $this->resolveAuthorIdentity($request);
        if (! $identity['id']) {
            abort(403, 'Author identity not available.');
        }

        ProjectTaskMessage::create([
            'project_task_id' => $task->id,
            'author_type' => $identity['type'],
            'author_id' => $identity['id'],
            'message' => $message,
            'attachment_path' => $attachmentPath,
            'reply_to_message_id' => $replyToMessageId,
        ]);

        return back()->with('status', 'Message sent.');
    }

    public function storeMessage(StoreTaskChatMessageRequest $request, Project $project, ProjectTask $task): JsonResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('comment', $task);

        $data = $request->validated();
        $replyData = $request->validate([
            'reply_to_message_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $message = isset($data['message']) ? trim((string) $data['message']) : null;
        $message = $message === '' ? null : $message;
        $replyToMessageId = $this->resolveReplyTarget($task, (int) ($replyData['reply_to_message_id'] ?? 0));

        if (! $request->hasFile('attachment')) {
            $duplicate = $this->findRecentDuplicate($task, $request, $message);
            if ($duplicate) {
                $duplicate->load(['userAuthor', 'employeeAuthor', 'salesRepAuthor', 'replyToMessage']);
                $identity = $this->resolveAuthorIdentity($request);
                $routePrefix = $this->resolveRoutePrefix($request);
                $attachmentRouteName = $routePrefix . '.projects.tasks.messages.attachment';
                $messageUpdateRouteName = $routePrefix . '.projects.tasks.chat.messages.update';
                $messageDeleteRouteName = $routePrefix . '.projects.tasks.chat.messages.destroy';
                $messagePinRouteName = $routePrefix . '.projects.tasks.chat.messages.pin';
                $messageReactionRouteName = $routePrefix . '.projects.tasks.chat.messages.react';

                $item = $this->messageItem(
                    $duplicate,
                    $project,
                    $task,
                    $attachmentRouteName,
                    $identity,
                    $messageUpdateRouteName,
                    $messageDeleteRouteName,
                    $messagePinRouteName,
                    $messageReactionRouteName
                );

                return response()->json([
                    'ok' => true,
                    'message' => 'Message sent.',
                    'data' => [
                        'item' => $item,
                        'next_after_id' => $duplicate->id,
                        'next_before_id' => $duplicate->id,
                    ],
                ]);
            }
        }

        $attachmentPath = $this->storeAttachment($request, $task);
        if (! $attachmentPath && $message === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Message cannot be empty.',
            ], 422);
        }

        $identity = $this->resolveAuthorIdentity($request);
        if (! $identity['id']) {
            abort(403, 'Author identity not available.');
        }

        $messageModel = ProjectTaskMessage::create([
            'project_task_id' => $task->id,
            'author_type' => $identity['type'],
            'author_id' => $identity['id'],
            'message' => $message,
            'attachment_path' => $attachmentPath,
            'reply_to_message_id' => $replyToMessageId,
        ]);
        $messageModel->load(['userAuthor', 'employeeAuthor', 'salesRepAuthor', 'replyToMessage']);

        $routePrefix = $this->resolveRoutePrefix($request);
        $attachmentRouteName = $routePrefix . '.projects.tasks.messages.attachment';
        $messageUpdateRouteName = $routePrefix . '.projects.tasks.chat.messages.update';
        $messageDeleteRouteName = $routePrefix . '.projects.tasks.chat.messages.destroy';
        $messagePinRouteName = $routePrefix . '.projects.tasks.chat.messages.pin';
        $messageReactionRouteName = $routePrefix . '.projects.tasks.chat.messages.react';
        $item = $this->messageItem(
            $messageModel,
            $project,
            $task,
            $attachmentRouteName,
            $identity,
            $messageUpdateRouteName,
            $messageDeleteRouteName,
            $messagePinRouteName,
            $messageReactionRouteName
        );

        return response()->json([
            'ok' => true,
            'message' => 'Message sent.',
            'data' => [
                'item' => $item,
                'next_after_id' => $messageModel->id,
                'next_before_id' => $messageModel->id,
            ],
        ]);
    }

    public function updateMessage(
        Request $request,
        Project $project,
        ProjectTask $task,
        ProjectTaskMessage $message
    ): JsonResponse {
        $this->ensureTaskBelongsToProject($project, $task);
        if ($message->project_task_id !== $task->id) {
            abort(404);
        }

        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $task);

        $identity = $this->resolveAuthorIdentity($request);
        if (! $identity['id']) {
            abort(403, 'Author identity not available.');
        }

        $guardError = $this->messageMutationGuard($message, $identity);
        if ($guardError) {
            return $guardError;
        }

        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $nextMessage = isset($data['message']) ? trim((string) $data['message']) : '';
        $nextMessage = $nextMessage === '' ? null : $nextMessage;

        if ($nextMessage === null && ! $message->attachment_path) {
            return response()->json([
                'ok' => false,
                'message' => 'Message cannot be empty.',
            ], 422);
        }

        $message->update([
            'message' => $nextMessage,
        ]);
        $message->load(['userAuthor', 'employeeAuthor', 'salesRepAuthor', 'replyToMessage']);

        $routePrefix = $this->resolveRoutePrefix($request);
        $attachmentRouteName = $routePrefix . '.projects.tasks.messages.attachment';
        $messageUpdateRouteName = $routePrefix . '.projects.tasks.chat.messages.update';
        $messageDeleteRouteName = $routePrefix . '.projects.tasks.chat.messages.destroy';
        $messagePinRouteName = $routePrefix . '.projects.tasks.chat.messages.pin';
        $messageReactionRouteName = $routePrefix . '.projects.tasks.chat.messages.react';
        $item = $this->messageItem(
            $message,
            $project,
            $task,
            $attachmentRouteName,
            $identity,
            $messageUpdateRouteName,
            $messageDeleteRouteName,
            $messagePinRouteName,
            $messageReactionRouteName
        );

        return response()->json([
            'ok' => true,
            'message' => 'Message updated.',
            'data' => [
                'item' => $item,
            ],
        ]);
    }

    public function destroyMessage(
        Request $request,
        Project $project,
        ProjectTask $task,
        ProjectTaskMessage $message
    ): JsonResponse {
        $this->ensureTaskBelongsToProject($project, $task);
        if ($message->project_task_id !== $task->id) {
            abort(404);
        }

        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $task);

        $identity = $this->resolveAuthorIdentity($request);
        if (! $identity['id']) {
            abort(403, 'Author identity not available.');
        }

        $guardError = $this->messageMutationGuard($message, $identity);
        if ($guardError) {
            return $guardError;
        }

        $attachmentPath = $message->attachment_path;
        $deletedId = $message->id;
        $message->delete();

        if ($attachmentPath) {
            Storage::disk('public')->delete($attachmentPath);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Message deleted.',
            'data' => [
                'id' => $deletedId,
            ],
        ]);
    }

    public function togglePin(Request $request, Project $project, ProjectTask $task, ProjectTaskMessage $message): JsonResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        if ($message->project_task_id !== $task->id) {
            abort(404);
        }

        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $task);

        $identity = $this->resolveAuthorIdentity($request);
        if (! $identity['id']) {
            abort(403, 'Author identity not available.');
        }

        $previousPinnedId = (int) ($task->messages()->where('is_pinned', true)->value('id') ?? 0);
        $nextPinnedId = 0;

        if (! $message->is_pinned) {
            $task->messages()->where('is_pinned', true)->update([
                'is_pinned' => false,
                'pinned_by_type' => null,
                'pinned_by_id' => null,
                'pinned_at' => null,
            ]);

            $message->update([
                'is_pinned' => true,
                'pinned_by_type' => $identity['type'],
                'pinned_by_id' => (int) $identity['id'],
                'pinned_at' => now(),
            ]);
            $nextPinnedId = $message->id;
        } else {
            $message->update([
                'is_pinned' => false,
                'pinned_by_type' => null,
                'pinned_by_id' => null,
                'pinned_at' => null,
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => $nextPinnedId > 0 ? 'Message pinned.' : 'Message unpinned.',
            'data' => [
                'message_id' => $message->id,
                'previous_pinned_id' => $previousPinnedId,
                'pinned_message_id' => $nextPinnedId,
            ],
        ]);
    }

    public function toggleReaction(Request $request, Project $project, ProjectTask $task, ProjectTaskMessage $message): JsonResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        if ($message->project_task_id !== $task->id) {
            abort(404);
        }

        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $task);

        $identity = $this->resolveAuthorIdentity($request);
        if (! $identity['id']) {
            abort(403, 'Author identity not available.');
        }

        $data = $request->validate([
            'emoji' => ['required', 'string', Rule::in(['👍', '❤️', '😂', '😮', '🙏'])],
        ]);
        $emoji = (string) $data['emoji'];

        $reactions = collect((array) ($message->reactions ?? []))->values();
        $existingIndex = $reactions->search(function ($reaction) use ($emoji, $identity) {
            return (string) ($reaction['emoji'] ?? '') === $emoji
                && (string) ($reaction['author_type'] ?? '') === (string) $identity['type']
                && (int) ($reaction['author_id'] ?? 0) === (int) $identity['id'];
        });

        if ($existingIndex !== false) {
            $reactions->forget($existingIndex);
        } else {
            $reactions->push([
                'emoji' => $emoji,
                'author_type' => $identity['type'],
                'author_id' => (int) $identity['id'],
                'at' => now()->toIso8601String(),
            ]);
        }

        $message->update([
            'reactions' => $reactions->values()->all(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Reaction updated.',
            'data' => [
                'message_id' => $message->id,
                'reactions' => $this->reactionSummary($message->refresh(), $identity),
            ],
        ]);
    }

    public function markRead(Request $request, Project $project, ProjectTask $task): JsonResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $task);

        $data = $request->validate([
            'last_read_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $identity = $this->resolveAuthorIdentity($request);
        if (! $identity['id']) {
            abort(403, 'Reader identity not available.');
        }

        $lastReadId = (int) ($data['last_read_id'] ?? 0);
        if ($lastReadId > 0) {
            $exists = $task->messages()->whereKey($lastReadId)->exists();
            if (! $exists) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Invalid message reference.',
                ], 422);
            }
        } else {
            $lastReadId = (int) ($task->messages()->max('id') ?? 0);
        }

        $read = ProjectTaskMessageRead::query()
            ->where('project_task_id', $task->id)
            ->where('reader_type', $identity['type'])
            ->where('reader_id', $identity['id'])
            ->first();

        $previous = $read?->last_read_message_id ?? 0;
        $nextReadId = max($previous, $lastReadId);

        if (! $read) {
            $read = ProjectTaskMessageRead::create([
                'project_task_id' => $task->id,
                'reader_type' => $identity['type'],
                'reader_id' => $identity['id'],
                'last_read_message_id' => $nextReadId,
                'read_at' => now(),
            ]);
        } else {
            $read->update([
                'last_read_message_id' => $nextReadId,
                'read_at' => now(),
            ]);
        }

        $unreadCount = $task->messages()
            ->where('id', '>', $nextReadId)
            ->count();

        return response()->json([
            'ok' => true,
            'data' => [
                'last_read_id' => $nextReadId,
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    public function attachment(Request $request, Project $project, ProjectTask $task, ProjectTaskMessage $message)
    {
        $this->ensureTaskBelongsToProject($project, $task);
        if ($message->project_task_id !== $task->id) {
            abort(404);
        }

        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $task);

        if (! $message->attachment_path) {
            abort(404);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($message->attachment_path)) {
            abort(404);
        }

        if ($message->isImageAttachment()) {
            return $disk->response($message->attachment_path);
        }

        return $disk->download($message->attachment_path, $message->attachmentName() ?? 'attachment');
    }

    public function inlineAttachment(ProjectTaskMessage $message)
    {
        if (! $message->attachment_path || ! $message->isImageAttachment()) {
            abort(404);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($message->attachment_path)) {
            abort(404);
        }

        return $disk->response($message->attachment_path);
    }

    private function ensureTaskBelongsToProject(Project $project, ProjectTask $task): void
    {
        if ($task->project_id !== $project->id) {
            abort(404);
        }
    }

    private function resolveActor(Request $request): object
    {
        $user = $request->user();
        if ($user instanceof Employee) {
            return $user;
        }

        $employee = $request->attributes->get('employee');
        if ($employee instanceof Employee) {
            return $employee;
        }

        if ($user) {
            if (method_exists($user, 'isEmployee') && $user->isEmployee() && $user->employee) {
                return $user->employee;
            }

            return $user;
        }

        abort(403, 'Authentication required.');
    }

    private function resolveAuthorIdentity(Request $request): array
    {
        $employee = $request->attributes->get('employee');
        if ($employee instanceof Employee) {
            return ['type' => 'employee', 'id' => $employee->id];
        }

        $salesRep = $request->attributes->get('salesRep');
        if ($salesRep instanceof SalesRepresentative) {
            return ['type' => 'sales_rep', 'id' => $salesRep->id];
        }

        $user = $request->user();
        return ['type' => 'user', 'id' => $user?->id];
    }

    private function resolveRoutePrefix(Request $request): string
    {
        $name = (string) $request->route()?->getName();
        $prefix = strstr($name, '.', true);
        if (in_array($prefix, ['admin', 'employee', 'client', 'rep'], true)) {
            return $prefix;
        }

        return 'admin';
    }

    private function storeAttachment(Request $request, ProjectTask $task): ?string
    {
        if (! $request->hasFile('attachment')) {
            return null;
        }

        $file = $request->file('attachment');
        $name = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
        $name = $name !== '' ? Str::slug($name) : 'attachment';
        $extension = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));
        if ($extension === '') {
            $mimeType = (string) ($file->getMimeType() ?? '');
            $extension = str_starts_with($mimeType, 'image/') ? 'jpg' : 'bin';
        }
        $fileName = $name . '-' . Str::random(8) . '.' . $extension;

        return $file->storeAs('project-task-messages/' . $task->id, $fileName, 'public');
    }

    private function findRecentDuplicate(ProjectTask $task, Request $request, ?string $message): ?ProjectTaskMessage
    {
        if ($message === null) {
            return null;
        }

        $identity = $this->resolveAuthorIdentity($request);
        if (! $identity['id']) {
            return null;
        }

        return ProjectTaskMessage::query()
            ->where('project_task_id', $task->id)
            ->where('author_type', $identity['type'])
            ->where('author_id', $identity['id'])
            ->where('message', $message)
            ->where('created_at', '>=', now()->subSeconds(5))
            ->latest('id')
            ->first();
    }

    private function messageItem(
        ProjectTaskMessage $message,
        Project $project,
        ProjectTask $task,
        string $attachmentRouteName,
        array $identity,
        string $updateRouteName,
        string $deleteRouteName,
        string $pinRouteName,
        string $reactionRouteName
    ): array {
        return [
            'id' => $message->id,
            'meta' => [
                'is_pinned' => (bool) $message->is_pinned,
                'reply_to_message_id' => (int) ($message->reply_to_message_id ?? 0),
            ],
            'message' => $this->messagePayload(
                $message,
                $project,
                $task,
                $attachmentRouteName,
                $identity,
                $updateRouteName,
                $deleteRouteName,
                $pinRouteName,
                $reactionRouteName
            ),
        ];
    }

    private function messagePayload(
        ProjectTaskMessage $message,
        Project $project,
        ProjectTask $task,
        string $attachmentRouteName,
        array $identity,
        string $updateRouteName,
        string $deleteRouteName,
        string $pinRouteName,
        string $reactionRouteName
    ): array {
        return [
            'id' => $message->id,
            'author_name' => $message->authorName(),
            'author_type' => (string) $message->author_type,
            'author_type_label' => $message->authorTypeLabel(),
            'message' => (string) ($message->message ?? ''),
            'created_at' => $message->created_at?->toIso8601String(),
            'created_at_display' => DateTimeFormat::formatDateTime($message->created_at),
            'attachment_url' => $message->attachment_path
                ? route($attachmentRouteName, [$project, $task, $message])
                : null,
            'attachment_name' => $message->attachmentName(),
            'attachment_is_image' => $message->isImageAttachment(),
            'reply_to_message_id' => (int) ($message->reply_to_message_id ?? 0),
            'reply_to_message_text' => (string) ($message->replyToMessage?->message ?? ''),
            'is_pinned' => (bool) $message->is_pinned,
            'can_edit' => (string) $message->author_type === (string) $identity['type']
                && (int) $message->author_id === (int) ($identity['id'] ?? 0),
            'reactions' => $this->reactionSummary($message, $identity),
            'routes' => [
                'update' => route($updateRouteName, [$project, $task, $message]),
                'delete' => route($deleteRouteName, [$project, $task, $message]),
                'pin' => route($pinRouteName, [$project, $task, $message]),
                'react' => route($reactionRouteName, [$project, $task, $message]),
            ],
        ];
    }

    private function reactionSummary(ProjectTaskMessage $message, array $identity): array
    {
        $normalized = collect((array) ($message->reactions ?? []))
            ->filter(function ($reaction) {
                return is_array($reaction)
                    && is_string($reaction['emoji'] ?? null)
                    && ($reaction['emoji'] ?? '') !== '';
            })
            ->values();

        return $normalized
            ->groupBy(fn ($reaction) => (string) ($reaction['emoji'] ?? ''))
            ->map(function ($items, $emoji) use ($identity) {
                return [
                    'emoji' => (string) $emoji,
                    'count' => $items->count(),
                    'reacted' => $items->contains(function ($item) use ($identity) {
                        return (string) ($item['author_type'] ?? '') === (string) $identity['type']
                            && (int) ($item['author_id'] ?? 0) === (int) $identity['id'];
                    }),
                ];
            })
            ->sortBy('emoji')
            ->values()
            ->all();
    }

    private function resolveReplyTarget(ProjectTask $task, int $replyToMessageId): ?int
    {
        if ($replyToMessageId <= 0) {
            return null;
        }

        $exists = $task->messages()->whereKey($replyToMessageId)->exists();
        if (! $exists) {
            abort(422, 'Invalid reply reference.');
        }

        return $replyToMessageId;
    }

    private function messageMutationGuard(ProjectTaskMessage $message, array $identity): ?JsonResponse
    {
        if ($message->author_type !== $identity['type']
            || (string) $message->author_id !== (string) $identity['id']) {
            return response()->json([
                'ok' => false,
                'message' => 'You can only edit or delete your own message.',
            ], 403);
        }

        $canEditUntil = $message->created_at?->copy()->addSeconds(self::EDITABLE_WINDOW_SECONDS);
        if (! $canEditUntil || now()->greaterThanOrEqualTo($canEditUntil)) {
            return response()->json([
                'ok' => false,
                'message' => 'You can edit or delete only within 30 seconds of sending.',
            ], 422);
        }

        return null;
    }

}
