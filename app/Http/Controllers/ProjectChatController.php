<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\ProjectMessageRead;
use App\Models\ProjectTask;
use App\Models\UserSession;
use App\Models\SalesRepresentative;
use App\Models\User;
use App\Http\Requests\StoreTaskChatMessageRequest;
use App\Notifications\ProjectChatMentionNotification;
use App\Services\ChatAiService;
use App\Services\GeminiService;
use App\Services\ChatAiSummaryCache;
use App\Support\ChatPresence;
use App\Support\ChatMentions;
use App\Support\DateTimeFormat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProjectChatController extends Controller
{
    private const EDITABLE_WINDOW_SECONDS = 30;

    public function show(Request $request, Project $project, ChatAiSummaryCache $summaryCache): InertiaResponse
    {
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $project);
        $this->touchPresence($request);

        $latestMessageId = (int) ($project->messages()->max('id') ?? 0);
        $routePrefix = $this->resolveRoutePrefix($request);
        $messages = $project->messages()
            ->with(['userAuthor', 'employeeAuthor', 'salesRepAuthor', 'replyToMessage'])
            ->latest('id')
            ->limit(30)
            ->get()
            ->reverse()
            ->values();

        $identity = $this->resolveAuthorIdentity($request);
        $attachmentRouteName = $routePrefix . '.projects.chat.messages.attachment';
        $taskShowRouteName = $routePrefix . '.projects.tasks.show';
        $messageUpdateRouteName = $routePrefix . '.projects.chat.messages.update';
        $messageDeleteRouteName = $routePrefix . '.projects.chat.messages.destroy';
        $messagePinRouteName = $routePrefix . '.projects.chat.messages.pin';
        $messageReactionRouteName = $routePrefix . '.projects.chat.messages.react';
        $readReceipts = $this->readReceiptsForMessages($project, $messages, $identity);
        $authorStatuses = ChatPresence::authorStatusesForMessages($messages);
        $messageMentions = $this->mentionsForMessages($messages);
        $participants = $this->participantsForProject($project, $identity);
        $mentionables = $this->mentionablesForProject($project, $identity, $participants);
        $participantStatuses = $this->participantStatuses($participants);
        $allParticipantsReadUpTo = $latestMessageId > 0
            ? $this->allParticipantsReadUpTo($project, $identity)
            : null;

        $canPost = Gate::forUser($actor)->check('view', $project);

        if ($latestMessageId > 0 && $identity['id']) {
            $this->markProjectChatReadUpTo($project, $identity, $latestMessageId);
        }

        $initialItems = $messages->map(fn (ProjectMessage $message) => $this->messageItem(
            $message,
            $project,
            $attachmentRouteName,
            $taskShowRouteName,
            $identity,
            $readReceipts[$message->id] ?? [],
            $authorStatuses[$message->author_type . ':' . $message->author_id] ?? 'offline',
            $messageMentions[$message->id] ?? [],
            $latestMessageId,
            $allParticipantsReadUpTo,
            $messageUpdateRouteName,
            $messageDeleteRouteName,
            $messagePinRouteName,
            $messageReactionRouteName
        ))->values();

        return Inertia::render('Projects/ProjectChat', [
            'pageTitle' => 'Project Chat',
            'pageHeading' => 'Project Chat',
            'pageKey' => $routePrefix . '.projects.chat',
            'routePrefix' => $routePrefix,
            'project' => [
                'id' => $project->id,
                'name' => (string) $project->name,
            ],
            'initialItems' => $initialItems->all(),
            'canPost' => $canPost,
            'aiReady' => (bool) config('google_ai.api_key'),
            'pinnedSummary' => $summaryCache->getProject($project->id) ?? [],
            'participants' => $participants,
            'mentionables' => $mentionables,
            'participantStatuses' => $participantStatuses,
            'latestMessageId' => $latestMessageId,
            'allParticipantsReadUpTo' => $allParticipantsReadUpTo,
            'editableWindowSeconds' => self::EDITABLE_WINDOW_SECONDS,
            'routes' => [
                'back' => route($routePrefix . '.projects.show', $project),
                'messages' => route($routePrefix . '.projects.chat.messages', $project),
                'storeForm' => route($routePrefix . '.projects.chat.store', $project),
                'storeMessage' => route($routePrefix . '.projects.chat.messages.store', $project),
                'read' => route($routePrefix . '.projects.chat.read', $project),
                'participants' => route($routePrefix . '.projects.chat.participants', $project),
                'presence' => route($routePrefix . '.projects.chat.presence', $project),
                'stream' => route($routePrefix . '.projects.chat.stream', $project),
                'aiSummary' => route($routePrefix . '.projects.chat.ai', $project),
            ],
        ]);
    }

    public function aiSummary(
        Request $request,
        Project $project,
        ChatAiService $aiService,
        GeminiService $geminiService,
        ChatAiSummaryCache $summaryCache
    ): JsonResponse {
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $project);

        if (! config('google_ai.api_key')) {
            return response()->json(['error' => 'Missing GOOGLE_AI_API_KEY.'], 422);
        }

        try {
            $result = $aiService->analyzeProjectChat($project, $geminiService);
            if (is_array($result['data'] ?? null)) {
                $result['data']['generated_at'] = now()->toDateTimeString();
                $summaryCache->putProject($project->id, $result['data']);
            }

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function messages(Request $request, Project $project): JsonResponse
    {
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $project);
        $this->touchPresence($request);

        $limit = min(max((int) $request->query('limit', 30), 1), 100);
        $afterId = (int) $request->query('after_id', 0);
        $beforeId = (int) $request->query('before_id', 0);

        $query = $project->messages()
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
        $attachmentRouteName = $routePrefix . '.projects.chat.messages.attachment';
        $taskShowRouteName = $routePrefix . '.projects.tasks.show';
        $messageUpdateRouteName = $routePrefix . '.projects.chat.messages.update';
        $messageDeleteRouteName = $routePrefix . '.projects.chat.messages.destroy';
        $messagePinRouteName = $routePrefix . '.projects.chat.messages.pin';
        $messageReactionRouteName = $routePrefix . '.projects.chat.messages.react';
        $readReceipts = $this->readReceiptsForMessages($project, $messages, $identity);
        $authorStatuses = ChatPresence::authorStatusesForMessages($messages);
        $messageMentions = $this->mentionsForMessages($messages);
        $latestMessageId = (int) ($project->messages()->max('id') ?? 0);
        $allParticipantsReadUpTo = $latestMessageId > 0
            ? $this->allParticipantsReadUpTo($project, $identity)
            : null;

        $items = $messages->map(fn (ProjectMessage $message) => $this->messageItem(
            $message,
            $project,
            $attachmentRouteName,
            $taskShowRouteName,
            $identity,
            $readReceipts[$message->id] ?? [],
            $authorStatuses[$message->author_type . ':' . $message->author_id] ?? 'offline',
            $messageMentions[$message->id] ?? [],
            $latestMessageId,
            $allParticipantsReadUpTo,
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

    public function participants(Request $request, Project $project): JsonResponse
    {
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $project);
        $this->touchPresence($request);

        $identity = $this->resolveAuthorIdentity($request);
        $mentionables = $this->mentionablesForProject($project, $identity);

        $query = trim((string) $request->query('q', ''));
        if ($query !== '') {
            $mentionables = array_values(array_filter($mentionables, function ($participant) use ($query) {
                return stripos((string) ($participant['label'] ?? ''), $query) !== false;
            }));
        }

        $limit = min(max((int) $request->query('limit', 20), 1), 50);
        $mentionables = array_slice($mentionables, 0, $limit);

        return response()->json([
            'ok' => true,
            'data' => [
                'items' => $mentionables,
            ],
        ]);
    }

    public function presence(Request $request, Project $project): JsonResponse
    {
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $project);
        $this->touchPresence($request);

        $data = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['active', 'idle'])],
        ]);

        $identity = $this->resolveAuthorIdentity($request);
        if (! $identity['id']) {
            abort(403, 'Author identity not available.');
        }

        $status = $data['status'] ?? 'active';
        ChatPresence::reportPresence($identity['type'], (int) $identity['id'], $status);

        return response()->json([
            'ok' => true,
            'data' => [
                'status' => $status,
            ],
        ]);
    }

    public function stream(Request $request, Project $project): StreamedResponse
    {
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $project);
        $this->touchPresence($request);

        $identity = $this->resolveAuthorIdentity($request);
        $routePrefix = $this->resolveRoutePrefix($request);
        $attachmentRouteName = $routePrefix . '.projects.chat.messages.attachment';
        $taskShowRouteName = $routePrefix . '.projects.tasks.show';
        $messageUpdateRouteName = $routePrefix . '.projects.chat.messages.update';
        $messageDeleteRouteName = $routePrefix . '.projects.chat.messages.destroy';
        $messagePinRouteName = $routePrefix . '.projects.chat.messages.pin';
        $messageReactionRouteName = $routePrefix . '.projects.chat.messages.react';
        $participants = $this->participantsForProject($project, $identity);
        $participantKeys = array_values(array_filter(array_map(
            fn ($participant) => $participant['key'] ?? null,
            $participants
        )));

        $cursor = max(
            (int) $request->query('after_id', 0),
            (int) $request->header('Last-Event-ID', 0)
        );

        return response()->stream(function () use (
            $project,
            $identity,
            $attachmentRouteName,
            $taskShowRouteName,
            $messageUpdateRouteName,
            $messageDeleteRouteName,
            $messagePinRouteName,
            $messageReactionRouteName,
            $participantKeys,
            $cursor
        ) {
            $lastId = $cursor;
            $startedAt = now();
            $lastPresencePayload = null;
            $lastPresenceSent = microtime(true);
            $presenceInterval = 2.0;

            while (true) {
                if (connection_aborted()) {
                    break;
                }

                $messages = $project->messages()
                    ->with(['userAuthor', 'employeeAuthor', 'salesRepAuthor', 'replyToMessage'])
                    ->where('id', '>', $lastId)
                    ->orderBy('id')
                    ->limit(50)
                    ->get();

                if ($messages->isNotEmpty()) {
                    $readReceipts = $this->readReceiptsForMessages($project, $messages, $identity);
                    $authorStatuses = ChatPresence::authorStatusesForMessages($messages);
                    $messageMentions = $this->mentionsForMessages($messages);
                    $latestMessageId = (int) ($project->messages()->max('id') ?? $lastId);
                    $allParticipantsReadUpTo = $latestMessageId > 0
                        ? $this->allParticipantsReadUpTo($project, $identity)
                        : null;

                    $items = $messages->map(fn (ProjectMessage $message) => $this->messageItem(
                        $message,
                        $project,
                        $attachmentRouteName,
                        $taskShowRouteName,
                        $identity,
                        $readReceipts[$message->id] ?? [],
                        $authorStatuses[$message->author_type . ':' . $message->author_id] ?? 'offline',
                        $messageMentions[$message->id] ?? [],
                        $latestMessageId,
                        $allParticipantsReadUpTo,
                        $messageUpdateRouteName,
                        $messageDeleteRouteName,
                        $messagePinRouteName,
                        $messageReactionRouteName
                    ))->values();

                    $lastId = (int) ($messages->max('id') ?? $lastId);
                    $payload = [
                        'items' => $items,
                        'last_id' => $lastId,
                    ];

                    echo "event: messages\n";
                    echo "id: {$lastId}\n";
                    echo 'data: ' . json_encode($payload) . "\n\n";
                }

                $now = microtime(true);
                if ($now - $lastPresenceSent >= $presenceInterval && ! empty($participantKeys)) {
                    $presence = ChatPresence::participantStatuses($participantKeys);
                    if ($presence !== $lastPresencePayload) {
                        $lastPresencePayload = $presence;
                        echo "event: presence\n";
                        echo 'data: ' . json_encode(['statuses' => $presence]) . "\n\n";
                    }
                    $lastPresenceSent = $now;
                }

                echo ": ping\n\n";
                @ob_flush();
                flush();

                if ($startedAt->diffInSeconds(now()) >= 25) {
                    break;
                }

                usleep(500000);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function store(StoreTaskChatMessageRequest $request, Project $project): RedirectResponse
    {
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $project);
        $this->touchPresence($request);

        $data = $request->validated();
        $replyData = $request->validate([
            'reply_to_message_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $message = isset($data['message']) ? trim((string) $data['message']) : null;
        $message = $message === '' ? null : $message;
        $replyToMessageId = $this->resolveReplyTarget($project, (int) ($replyData['reply_to_message_id'] ?? 0));

        if (! $request->hasFile('attachment')) {
            $duplicate = $this->findRecentDuplicate($project, $request, $message);
            if ($duplicate) {
                return back()->with('status', 'Message sent.');
            }
        }

        $attachmentPath = $this->storeAttachment($request, $project);
        if (! $attachmentPath && $message === null) {
            return back()->withErrors(['message' => 'Message cannot be empty.']);
        }

        $identity = $this->resolveAuthorIdentity($request);
        if (! $identity['id']) {
            abort(403, 'Author identity not available.');
        }

        $mentions = $this->resolveMentions($project, $identity, $message, $data['mentions'] ?? null);

        $createdMessage = ProjectMessage::create([
            'project_id' => $project->id,
            'author_type' => $identity['type'],
            'author_id' => $identity['id'],
            'message' => $message,
            'mentions' => $mentions ?: null,
            'attachment_path' => $attachmentPath,
            'reply_to_message_id' => $replyToMessageId,
        ]);
        $this->notifyMentionedParticipants($project, $createdMessage, $identity);

        return back()->with('status', 'Message sent.');
    }

    public function storeMessage(StoreTaskChatMessageRequest $request, Project $project): JsonResponse
    {
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $project);
        $this->touchPresence($request);

        $data = $request->validated();
        $replyData = $request->validate([
            'reply_to_message_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $message = isset($data['message']) ? trim((string) $data['message']) : null;
        $message = $message === '' ? null : $message;
        $replyToMessageId = $this->resolveReplyTarget($project, (int) ($replyData['reply_to_message_id'] ?? 0));

        if (! $request->hasFile('attachment')) {
            $duplicate = $this->findRecentDuplicate($project, $request, $message);
            if ($duplicate) {
                $duplicate->load(['userAuthor', 'employeeAuthor', 'salesRepAuthor', 'replyToMessage']);
                $identity = $this->resolveAuthorIdentity($request);
                $routePrefix = $this->resolveRoutePrefix($request);
                $attachmentRouteName = $routePrefix . '.projects.chat.messages.attachment';
                $taskShowRouteName = $routePrefix . '.projects.tasks.show';
                $messageUpdateRouteName = $routePrefix . '.projects.chat.messages.update';
                $messageDeleteRouteName = $routePrefix . '.projects.chat.messages.destroy';
                $messagePinRouteName = $routePrefix . '.projects.chat.messages.pin';
                $messageReactionRouteName = $routePrefix . '.projects.chat.messages.react';
                $readReceipts = $this->readReceiptsForMessages($project, collect([$duplicate]), $identity);
                $authorStatuses = ChatPresence::authorStatusesForMessages(collect([$duplicate]));
                $messageMentions = $this->mentionsForMessages(collect([$duplicate]));
                $allParticipantsReadUpTo = $this->allParticipantsReadUpTo($project, $identity);
                $item = $this->messageItem(
                    $duplicate,
                    $project,
                    $attachmentRouteName,
                    $taskShowRouteName,
                    $identity,
                    $readReceipts[$duplicate->id] ?? [],
                    $authorStatuses[$duplicate->author_type . ':' . $duplicate->author_id] ?? 'offline',
                    $messageMentions[$duplicate->id] ?? [],
                    $duplicate->id,
                    $allParticipantsReadUpTo,
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

        $attachmentPath = $this->storeAttachment($request, $project);
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

        $mentions = $this->resolveMentions($project, $identity, $message, $data['mentions'] ?? null);

        $messageModel = ProjectMessage::create([
            'project_id' => $project->id,
            'author_type' => $identity['type'],
            'author_id' => $identity['id'],
            'message' => $message,
            'mentions' => $mentions ?: null,
            'attachment_path' => $attachmentPath,
            'reply_to_message_id' => $replyToMessageId,
        ]);
        $messageModel->load(['userAuthor', 'employeeAuthor', 'salesRepAuthor', 'replyToMessage']);
        $this->notifyMentionedParticipants($project, $messageModel, $identity);

        $routePrefix = $this->resolveRoutePrefix($request);
        $attachmentRouteName = $routePrefix . '.projects.chat.messages.attachment';
        $taskShowRouteName = $routePrefix . '.projects.tasks.show';
        $messageUpdateRouteName = $routePrefix . '.projects.chat.messages.update';
        $messageDeleteRouteName = $routePrefix . '.projects.chat.messages.destroy';
        $messagePinRouteName = $routePrefix . '.projects.chat.messages.pin';
        $messageReactionRouteName = $routePrefix . '.projects.chat.messages.react';
        $readReceipts = $this->readReceiptsForMessages($project, collect([$messageModel]), $identity);
        $authorStatuses = ChatPresence::authorStatusesForMessages(collect([$messageModel]));
        $messageMentions = $this->mentionsForMessages(collect([$messageModel]));
        $allParticipantsReadUpTo = $this->allParticipantsReadUpTo($project, $identity);
        $item = $this->messageItem(
            $messageModel,
            $project,
            $attachmentRouteName,
            $taskShowRouteName,
            $identity,
            $readReceipts[$messageModel->id] ?? [],
            $authorStatuses[$messageModel->author_type . ':' . $messageModel->author_id] ?? 'offline',
            $messageMentions[$messageModel->id] ?? [],
            $messageModel->id,
            $allParticipantsReadUpTo,
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

    public function updateMessage(Request $request, Project $project, ProjectMessage $message): JsonResponse
    {
        if ($message->project_id !== $project->id) {
            abort(404);
        }

        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $project);
        $this->touchPresence($request);

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

        $mentions = $this->resolveMentions($project, $identity, $nextMessage, null);

        $message->update([
            'message' => $nextMessage,
            'mentions' => $mentions ?: null,
        ]);
        $message->load(['userAuthor', 'employeeAuthor', 'salesRepAuthor', 'replyToMessage']);

        $routePrefix = $this->resolveRoutePrefix($request);
        $attachmentRouteName = $routePrefix . '.projects.chat.messages.attachment';
        $taskShowRouteName = $routePrefix . '.projects.tasks.show';
        $messageUpdateRouteName = $routePrefix . '.projects.chat.messages.update';
        $messageDeleteRouteName = $routePrefix . '.projects.chat.messages.destroy';
        $messagePinRouteName = $routePrefix . '.projects.chat.messages.pin';
        $messageReactionRouteName = $routePrefix . '.projects.chat.messages.react';
        $readReceipts = $this->readReceiptsForMessages($project, collect([$message]), $identity);
        $authorStatuses = ChatPresence::authorStatusesForMessages(collect([$message]));
        $messageMentions = $this->mentionsForMessages(collect([$message]));
        $latestMessageId = (int) ($project->messages()->max('id') ?? 0);
        $allParticipantsReadUpTo = $latestMessageId > 0
            ? $this->allParticipantsReadUpTo($project, $identity)
            : null;

        $item = $this->messageItem(
            $message,
            $project,
            $attachmentRouteName,
            $taskShowRouteName,
            $identity,
            $readReceipts[$message->id] ?? [],
            $authorStatuses[$message->author_type . ':' . $message->author_id] ?? 'offline',
            $messageMentions[$message->id] ?? [],
            $latestMessageId,
            $allParticipantsReadUpTo,
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

    public function destroyMessage(Request $request, Project $project, ProjectMessage $message): JsonResponse
    {
        if ($message->project_id !== $project->id) {
            abort(404);
        }

        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $project);
        $this->touchPresence($request);

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

    public function togglePin(Request $request, Project $project, ProjectMessage $message): JsonResponse
    {
        if ($message->project_id !== $project->id) {
            abort(404);
        }

        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $project);
        $this->touchPresence($request);

        $identity = $this->resolveAuthorIdentity($request);
        if (! $identity['id']) {
            abort(403, 'Author identity not available.');
        }

        $previousPinnedId = (int) ($project->messages()->where('is_pinned', true)->value('id') ?? 0);
        $nextPinnedId = 0;

        if (! $message->is_pinned) {
            $project->messages()->where('is_pinned', true)->update([
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

    public function toggleReaction(Request $request, Project $project, ProjectMessage $message): JsonResponse
    {
        if ($message->project_id !== $project->id) {
            abort(404);
        }

        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $project);
        $this->touchPresence($request);

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

    public function markRead(Request $request, Project $project): JsonResponse
    {
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $project);
        $this->touchPresence($request);

        $data = $request->validate([
            'last_read_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $identity = $this->resolveAuthorIdentity($request);
        if (! $identity['id']) {
            abort(403, 'Reader identity not available.');
        }

        $lastReadId = (int) ($data['last_read_id'] ?? 0);
        if ($lastReadId > 0) {
            $exists = $project->messages()->whereKey($lastReadId)->exists();
            if (! $exists) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Invalid message reference.',
                ], 422);
            }
        } else {
            $lastReadId = (int) ($project->messages()->max('id') ?? 0);
        }

        $read = ProjectMessageRead::query()
            ->where('project_id', $project->id)
            ->where('reader_type', $identity['type'])
            ->where('reader_id', $identity['id'])
            ->first();

        $previous = $read?->last_read_message_id ?? 0;
        $nextReadId = max($previous, $lastReadId);

        if (! $read) {
            $read = ProjectMessageRead::create([
                'project_id' => $project->id,
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

        $unreadCount = $project->messages()
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

    public function attachment(Request $request, Project $project, ProjectMessage $message)
    {
        if ($message->project_id !== $project->id) {
            abort(404);
        }

        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $project);

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

    public function inlineAttachment(ProjectMessage $message)
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

    private function resolvePresenceUser(Request $request): ?object
    {
        $employee = $request->attributes->get('employee');
        if ($employee instanceof Employee) {
            return $employee;
        }

        $salesRep = $request->attributes->get('salesRep');
        if ($salesRep instanceof SalesRepresentative) {
            return $salesRep;
        }

        return $request->user();
    }

    private function markProjectChatReadUpTo(Project $project, array $identity, int $lastReadId): void
    {
        $read = ProjectMessageRead::query()
            ->where('project_id', $project->id)
            ->where('reader_type', $identity['type'])
            ->where('reader_id', $identity['id'])
            ->first();

        $previous = $read?->last_read_message_id ?? 0;
        $nextReadId = max($previous, $lastReadId);

        if (! $read) {
            ProjectMessageRead::create([
                'project_id' => $project->id,
                'reader_type' => $identity['type'],
                'reader_id' => $identity['id'],
                'last_read_message_id' => $nextReadId,
                'read_at' => now(),
            ]);
            return;
        }

        if ($nextReadId !== $previous) {
            $read->update([
                'last_read_message_id' => $nextReadId,
                'read_at' => now(),
            ]);
        }
    }

    private function findRecentDuplicate(Project $project, Request $request, ?string $message): ?ProjectMessage
    {
        if ($message === null) {
            return null;
        }

        $identity = $this->resolveAuthorIdentity($request);
        if (! $identity['id']) {
            return null;
        }

        return ProjectMessage::query()
            ->where('project_id', $project->id)
            ->where('author_type', $identity['type'])
            ->where('author_id', $identity['id'])
            ->where('message', $message)
            ->where('created_at', '>=', now()->subSeconds(5))
            ->latest('id')
            ->first();
    }

    private function touchPresence(Request $request): void
    {
        $user = $this->resolvePresenceUser($request);
        if (! $user) {
            return;
        }

        $now = now();
        $sessionTargets = [
            [
                'type' => get_class($user),
                'id' => (int) $user->id,
            ],
        ];

        // Employee/Sales sessions are authenticated through linked users.
        if ($user instanceof Employee || $user instanceof SalesRepresentative) {
            $linkedUserId = (int) ($user->user_id ?? 0);
            if ($linkedUserId > 0) {
                $sessionTargets[] = [
                    'type' => User::class,
                    'id' => $linkedUserId,
                ];
            }
        }

        foreach ($sessionTargets as $target) {
            UserSession::query()
                ->where('user_type', $target['type'])
                ->where('user_id', $target['id'])
                ->whereNull('logout_at')
                ->orderByDesc('login_at')
                ->limit(1)
                ->update(['last_seen_at' => $now]);
        }

        $identity = $this->resolveAuthorIdentity($request);
        if (($identity['id'] ?? 0) > 0) {
            ChatPresence::reportPresence((string) $identity['type'], (int) $identity['id'], 'active');
        }
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

    private function storeAttachment(Request $request, Project $project): ?string
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

        return $file->storeAs('project-messages/' . $project->id, $fileName, 'public');
    }

    private function resolveMentions(Project $project, array $identity, ?string $message, ?string $mentionsPayload): array
    {
        $message = $message !== null ? trim($message) : '';
        if ($message === '') {
            return [];
        }

        $mentionables = $this->mentionablesForProject($project, $identity);
        $submittedMentions = $this->decodeMentionsPayload($mentionsPayload);

        return ChatMentions::normalize($message, $mentionables, $submittedMentions);
    }

    private function decodeMentionsPayload(?string $payload): ?array
    {
        if (! is_string($payload) || trim($payload) === '') {
            return null;
        }

        $decoded = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function mentionsForMessages($messages): array
    {
        $messages = $messages instanceof \Illuminate\Support\Collection ? $messages : collect($messages);
        if ($messages->isEmpty()) {
            return [];
        }

        $idsByType = [
            'user' => [],
            'employee' => [],
            'sales_rep' => [],
            'project_task' => [],
        ];

        foreach ($messages as $message) {
            foreach ((array) ($message->mentions ?? []) as $mention) {
                $type = $this->normalizeMentionType($mention['type'] ?? '');
                $id = (int) ($mention['id'] ?? 0);
                if (! $type || $id <= 0) {
                    continue;
                }
                if (! array_key_exists($type, $idsByType)) {
                    continue;
                }
                $idsByType[$type][] = $id;
            }
        }

        $userNames = ! empty($idsByType['user'])
            ? User::whereIn('id', array_unique($idsByType['user']))->pluck('name', 'id')->all()
            : [];
        $employeeNames = ! empty($idsByType['employee'])
            ? Employee::whereIn('id', array_unique($idsByType['employee']))->pluck('name', 'id')->all()
            : [];
        $salesRepNames = ! empty($idsByType['sales_rep'])
            ? SalesRepresentative::whereIn('id', array_unique($idsByType['sales_rep']))->pluck('name', 'id')->all()
            : [];
        $taskTitles = ! empty($idsByType['project_task'])
            ? ProjectTask::whereIn('id', array_unique($idsByType['project_task']))->pluck('title', 'id')->all()
            : [];

        $mentionsByMessage = [];
        foreach ($messages as $message) {
            $matches = [];
            foreach ((array) ($message->mentions ?? []) as $mention) {
                $type = $this->normalizeMentionType($mention['type'] ?? '');
                $id = (int) ($mention['id'] ?? 0);
                $label = trim((string) ($mention['label'] ?? ''));

                if (! $type || $id <= 0 || $label === '') {
                    continue;
                }

                $display = $label;
                if ($type === 'employee') {
                    $display = $employeeNames[$id] ?? $label;
                } elseif ($type === 'sales_rep') {
                    $display = $salesRepNames[$id] ?? $label;
                } elseif ($type === 'project_task') {
                    $display = $taskTitles[$id] ?? $label;
                } else {
                    $display = $userNames[$id] ?? $label;
                }

                $matches[] = [
                    'type' => $type,
                    'id' => $id,
                    'label' => $label,
                    'display' => $display,
                ];
            }

            $mentionsByMessage[$message->id] = $matches;
        }

        return $mentionsByMessage;
    }

    private function mentionablesForProject(Project $project, array $identity, ?array $participants = null): array
    {
        $participants = $participants ?? $this->participantsForProject($project, $identity);
        $mentionables = $participants;

        $tasks = $project->tasks()
            ->orderByDesc('id')
            ->get(['id', 'title']);

        foreach ($tasks as $task) {
            $label = trim((string) ($task->title ?? ''));
            if ($label === '') {
                continue;
            }

            $mentionables[] = [
                'key' => 'project_task:' . $task->id,
                'type' => 'project_task',
                'id' => $task->id,
                'label' => $label,
                'role' => 'Task #' . $task->id,
            ];
        }

        usort($mentionables, fn ($a, $b) => strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? '')));

        return $mentionables;
    }

    private function participantsForProject(Project $project, array $identity): array
    {
        // Keep participant scope strict to the current project to avoid
        // leaking users from other projects under the same customer.
        $customerId = (int) ($project->customer_id ?? 0);
        $userIds = User::query()
            ->where(function ($query) use ($project, $customerId) {
                $query->where(function ($scope) use ($project) {
                    $scope->where('role', Role::CLIENT_PROJECT)
                        ->where('project_id', $project->id);
                });

                if ($customerId > 0) {
                    $query->orWhere(function ($scope) use ($customerId) {
                        $scope->whereIn('role', [Role::CLIENT, 'customer'])
                            ->where('customer_id', $customerId);
                    });
                }
            })
            ->pluck('id')
            ->all();
        $employeeIds = $project->employees()->pluck('employees.id')->all();
        $salesRepIds = $project->salesRepresentatives()->pluck('sales_representatives.id')->all();

        $authors = ProjectMessage::query()
            ->where('project_id', $project->id)
            ->select('author_type', 'author_id')
            ->distinct()
            ->get();
        $authorUserIds = $authors
            ->filter(fn ($author) => $this->normalizeMentionType($author->author_type ?? '') === 'user')
            ->pluck('author_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();
        $authorUserRoles = $authorUserIds->isNotEmpty()
            ? User::query()
                ->whereIn('id', $authorUserIds->all())
                ->pluck('role', 'id')
                ->all()
            : [];

        foreach ($authors as $author) {
            $type = $this->normalizeMentionType($author->author_type ?? '');
            $id = (int) ($author->author_id ?? 0);
            if ($id <= 0) {
                continue;
            }

            if ($type === 'employee') {
                $employeeIds[] = $id;
            } elseif ($type === 'sales_rep') {
                $salesRepIds[] = $id;
            } else {
                $role = $authorUserRoles[$id] ?? null;
                $isClientUser = in_array($role, [Role::CLIENT, Role::CLIENT_PROJECT], true);
                if ($isClientUser && ! in_array($id, $userIds, true)) {
                    continue;
                }
                $userIds[] = $id;
            }
        }

        if (! empty($identity['id'])) {
            if ($identity['type'] === 'employee') {
                $employeeIds[] = (int) $identity['id'];
            } elseif ($identity['type'] === 'sales_rep') {
                $salesRepIds[] = (int) $identity['id'];
            } else {
                $userIds[] = (int) $identity['id'];
            }
        }

        $users = ! empty($userIds)
            ? User::whereIn('id', array_unique($userIds))->get(['id', 'name', 'role'])
            : collect();
        $employees = ! empty($employeeIds)
            ? Employee::whereIn('id', array_unique($employeeIds))->get(['id', 'name'])
            : collect();
        $salesReps = ! empty($salesRepIds)
            ? SalesRepresentative::whereIn('id', array_unique($salesRepIds))->get(['id', 'name'])
            : collect();

        $participants = [];

        foreach ($users as $user) {
            $participants[] = [
                'key' => 'user:' . $user->id,
                'type' => 'user',
                'id' => $user->id,
                'label' => $user->name,
                'role' => $this->userRoleLabel($user),
            ];
        }

        foreach ($employees as $employee) {
            $participants[] = [
                'key' => 'employee:' . $employee->id,
                'type' => 'employee',
                'id' => $employee->id,
                'label' => $employee->name,
                'role' => 'Employee',
            ];
        }

        foreach ($salesReps as $salesRep) {
            $participants[] = [
                'key' => 'sales_rep:' . $salesRep->id,
                'type' => 'sales_rep',
                'id' => $salesRep->id,
                'label' => $salesRep->name,
                'role' => 'Sales Rep',
            ];
        }

        usort($participants, fn ($a, $b) => strcasecmp($a['label'], $b['label']));

        return $participants;
    }

    private function participantStatuses(array $participants): array
    {
        $keys = array_values(array_filter(array_map(
            fn ($participant) => $participant['key'] ?? null,
            $participants
        )));

        $statuses = ChatPresence::participantStatuses($keys);

        foreach ($keys as $key) {
            if (! array_key_exists($key, $statuses)) {
                $statuses[$key] = 'offline';
            }
        }

        return $statuses;
    }

    private function normalizeMentionType(?string $type): string
    {
        $type = strtolower((string) $type);
        if ($type === 'salesrep') {
            return 'sales_rep';
        }
        if ($type === 'projecttask' || $type === 'task') {
            return 'project_task';
        }

        return $type;
    }

    private function userRoleLabel(User $user): string
    {
        if ($user->isClientProject() || $user->isClient()) {
            return 'Client';
        }

        if ($user->isAdmin()) {
            return 'Admin';
        }

        if ($user->isSales()) {
            return 'Sales';
        }

        if ($user->isSupport()) {
            return 'Support';
        }

        return 'User';
    }

    private function messageSnippet(ProjectMessage $message): string
    {
        $text = trim((string) ($message->message ?? ''));
        if ($text === '') {
            return $message->attachment_path ? 'Attachment' : '';
        }

        return Str::limit($text, 120);
    }

    private function messageItem(
        ProjectMessage $message,
        Project $project,
        string $attachmentRouteName,
        string $taskShowRouteName,
        array $identity,
        array $seenBy,
        string $authorStatus,
        array $mentionMatches,
        int $latestMessageId,
        ?array $allParticipantsReadUpTo,
        string $updateRouteName,
        string $deleteRouteName,
        string $pinRouteName,
        string $reactionRouteName
    ): array {
        return [
            'id' => $message->id,
            'meta' => [
                'author' => $message->authorName(),
                'author_type' => $message->author_type,
                'author_id' => $message->author_id,
                'snippet' => $this->messageSnippet($message),
                'project' => $project->name,
                'is_pinned' => (bool) $message->is_pinned,
                'reply_to_message_id' => (int) ($message->reply_to_message_id ?? 0),
            ],
            'message' => $this->messagePayload(
                $message,
                $project,
                $attachmentRouteName,
                $taskShowRouteName,
                $identity,
                $seenBy,
                $authorStatus,
                $mentionMatches,
                $latestMessageId,
                $allParticipantsReadUpTo,
                $updateRouteName,
                $deleteRouteName,
                $pinRouteName,
                $reactionRouteName
            ),
        ];
    }

    private function messagePayload(
        ProjectMessage $message,
        Project $project,
        string $attachmentRouteName,
        string $taskShowRouteName,
        array $identity,
        array $seenBy,
        string $authorStatus,
        array $mentionMatches,
        int $latestMessageId,
        ?array $allParticipantsReadUpTo,
        string $updateRouteName,
        string $deleteRouteName,
        string $pinRouteName,
        string $reactionRouteName
    ): array {
        $taskLink = null;
        if ($message->replyToMessage?->project_task_id && \Route::has($taskShowRouteName)) {
            $taskLink = route($taskShowRouteName, [$project, $message->replyToMessage->project_task_id]);
        }

        return [
            'id' => $message->id,
            'author_name' => $message->authorName(),
            'author_type' => (string) $message->author_type,
            'author_type_label' => $message->authorTypeLabel(),
            'author_id' => (int) $message->author_id,
            'author_status' => $authorStatus,
            'message' => (string) ($message->message ?? ''),
            'created_at' => $message->created_at?->toIso8601String(),
            'created_at_display' => DateTimeFormat::formatDateTime($message->created_at),
            'attachment_url' => $message->attachment_path
                ? route($attachmentRouteName, [$project, $message])
                : null,
            'attachment_name' => $message->attachmentName(),
            'attachment_is_image' => $message->isImageAttachment(),
            'reply_to_message_id' => (int) ($message->reply_to_message_id ?? 0),
            'reply_to_message_text' => (string) ($message->replyToMessage?->message ?? ''),
            'reply_to_task_url' => $taskLink,
            'mentions' => array_values((array) ($message->mentions ?? [])),
            'mention_matches' => $mentionMatches,
            'seen_by' => $seenBy,
            'latest_message_id' => $latestMessageId,
            'all_participants_read_up_to' => $allParticipantsReadUpTo,
            'is_pinned' => (bool) $message->is_pinned,
            'can_edit' => (string) $message->author_type === (string) $identity['type']
                && (int) $message->author_id === (int) ($identity['id'] ?? 0),
            'reactions' => $this->reactionSummary($message, $identity),
            'routes' => [
                'update' => route($updateRouteName, [$project, $message]),
                'delete' => route($deleteRouteName, [$project, $message]),
                'pin' => route($pinRouteName, [$project, $message]),
                'react' => route($reactionRouteName, [$project, $message]),
            ],
        ];
    }

    private function reactionSummary(ProjectMessage $message, array $identity): array
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

    private function resolveReplyTarget(Project $project, int $replyToMessageId): ?int
    {
        if ($replyToMessageId <= 0) {
            return null;
        }

        $exists = $project->messages()->whereKey($replyToMessageId)->exists();
        if (! $exists) {
            abort(422, 'Invalid reply reference.');
        }

        return $replyToMessageId;
    }

    private function messageMutationGuard(ProjectMessage $message, array $identity): ?JsonResponse
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

    private function notifyMentionedParticipants(Project $project, ProjectMessage $message, array $identity): void
    {
        $mentions = collect((array) ($message->mentions ?? []))
            ->map(function ($mention) {
                $type = $this->normalizeMentionType($mention['type'] ?? '');
                $id = (int) ($mention['id'] ?? 0);
                return ['type' => $type, 'id' => $id];
            })
            ->filter(fn ($mention) => in_array($mention['type'], ['user', 'employee', 'sales_rep'], true) && $mention['id'] > 0)
            ->unique(fn ($mention) => $mention['type'] . ':' . $mention['id'])
            ->values();

        if ($mentions->isEmpty()) {
            return;
        }

        $authorName = $message->authorName();
        $snippet = $this->messageSnippet($message);
        $userIds = $mentions->where('type', 'user')->pluck('id')->all();
        $employeeIds = $mentions->where('type', 'employee')->pluck('id')->all();
        $salesRepIds = $mentions->where('type', 'sales_rep')->pluck('id')->all();

        if (! empty($userIds)) {
            $users = User::query()
                ->whereIn('id', $userIds)
                ->get(['id', 'name', 'email', 'role']);

            foreach ($users as $user) {
                $this->sendMentionMailToUser($project, $identity, $authorName, $snippet, $user);
            }
        }

        if (! empty($employeeIds)) {
            $employees = Employee::query()
                ->whereIn('id', $employeeIds)
                ->get(['id', 'name', 'email']);

            foreach ($employees as $employee) {
                $this->sendMentionMailToEmployee($project, $identity, $authorName, $snippet, $employee);
            }
        }

        if (! empty($salesRepIds)) {
            $salesReps = SalesRepresentative::query()
                ->whereIn('id', $salesRepIds)
                ->get(['id', 'name', 'email']);

            foreach ($salesReps as $salesRep) {
                $this->sendMentionMailToSalesRep($project, $identity, $authorName, $snippet, $salesRep);
            }
        }
    }

    private function sendMentionMailToUser(
        Project $project,
        array $identity,
        string $authorName,
        string $snippet,
        User $user
    ): void {
        if ($identity['type'] === 'user' && (int) $identity['id'] === (int) $user->id) {
            return;
        }

        $email = trim((string) ($user->email ?? ''));
        if ($email === '') {
            return;
        }

        [$chatRouteName, $loginRouteName, $loginLabel] = $this->routesForMentionedUser($user);
        $chatUrl = $this->safeRoute($chatRouteName, $project);
        $loginUrl = $this->safeRoute($loginRouteName);

        $this->deliverMentionNotification($email, $project, $authorName, $snippet, $chatUrl, $loginUrl, $loginLabel);
    }

    private function sendMentionMailToEmployee(
        Project $project,
        array $identity,
        string $authorName,
        string $snippet,
        Employee $employee
    ): void {
        if ($identity['type'] === 'employee' && (int) $identity['id'] === (int) $employee->id) {
            return;
        }

        $email = trim((string) ($employee->email ?? ''));
        if ($email === '') {
            return;
        }

        $chatUrl = $this->safeRoute('employee.projects.chat', $project);
        $loginUrl = $this->safeRoute('employee.login');
        $this->deliverMentionNotification($email, $project, $authorName, $snippet, $chatUrl, $loginUrl, 'Employee login');
    }

    private function sendMentionMailToSalesRep(
        Project $project,
        array $identity,
        string $authorName,
        string $snippet,
        SalesRepresentative $salesRep
    ): void {
        if ($identity['type'] === 'sales_rep' && (int) $identity['id'] === (int) $salesRep->id) {
            return;
        }

        $email = trim((string) ($salesRep->email ?? ''));
        if ($email === '') {
            return;
        }

        $chatUrl = $this->safeRoute('rep.projects.chat', $project);
        $loginUrl = $this->safeRoute('sales.login');
        $this->deliverMentionNotification($email, $project, $authorName, $snippet, $chatUrl, $loginUrl, 'Sales login');
    }

    private function deliverMentionNotification(
        string $email,
        Project $project,
        string $authorName,
        string $snippet,
        ?string $chatUrl,
        ?string $loginUrl,
        ?string $loginLabel
    ): void {
        try {
            Notification::route('mail', $email)->notify(new ProjectChatMentionNotification(
                $project,
                $authorName,
                $snippet,
                $chatUrl,
                $loginUrl,
                $loginLabel
            ));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function routesForMentionedUser(User $user): array
    {
        if ($user->isClient() || $user->isClientProject()) {
            return ['client.projects.chat', 'login', 'Client login'];
        }

        if ($user->isSales()) {
            return ['rep.projects.chat', 'sales.login', 'Sales login'];
        }

        if ($user->isSupport()) {
            return ['admin.projects.chat', 'support.login', 'Support login'];
        }

        return ['admin.projects.chat', 'admin.login', 'Admin login'];
    }

    private function safeRoute(string $routeName, mixed $parameters = []): ?string
    {
        try {
            return route($routeName, $parameters);
        } catch (\Throwable $exception) {
            report($exception);
            return null;
        }
    }

    private function readReceiptsForMessages(Project $project, $messages, array $identity): array
    {
        $messageIds = $messages->pluck('id')->filter()->values();
        if ($messageIds->isEmpty()) {
            return [];
        }

        $allowedParticipantKeys = collect($this->participantsForProject($project, $identity))
            ->map(fn ($participant) => ($participant['type'] ?? '') . ':' . ($participant['id'] ?? ''))
            ->filter()
            ->values()
            ->all();

        $minId = $messageIds->min();
        $reads = ProjectMessageRead::query()
            ->where('project_id', $project->id)
            ->whereNotNull('last_read_message_id')
            ->where('last_read_message_id', '>=', $minId)
            ->get(['reader_type', 'reader_id', 'last_read_message_id']);

        if ($identity['id']) {
            $reads = $reads->reject(fn ($read) => $read->reader_type === $identity['type']
                && (string) $read->reader_id === (string) $identity['id']);
        }
        if (! empty($allowedParticipantKeys)) {
            $reads = $reads->filter(function ($read) use ($allowedParticipantKeys) {
                $readerType = $this->normalizeMentionType((string) $read->reader_type);
                $key = $readerType . ':' . (int) $read->reader_id;
                return in_array($key, $allowedParticipantKeys, true);
            });
        }

        if ($reads->isEmpty()) {
            return $messageIds->mapWithKeys(fn ($id) => [$id => []])->all();
        }

        $userIds = $reads->where('reader_type', 'user')->pluck('reader_id')->unique()->values();
        $employeeIds = $reads->where('reader_type', 'employee')->pluck('reader_id')->unique()->values();
        $salesRepIds = $reads->filter(fn ($read) => in_array($read->reader_type, ['sales_rep', 'salesrep'], true))
            ->pluck('reader_id')
            ->unique()
            ->values();

        $userNames = $userIds->isNotEmpty()
            ? User::whereIn('id', $userIds)->pluck('name', 'id')->all()
            : [];
        $employeeNames = $employeeIds->isNotEmpty()
            ? Employee::whereIn('id', $employeeIds)->pluck('name', 'id')->all()
            : [];
        $salesRepNames = $salesRepIds->isNotEmpty()
            ? SalesRepresentative::whereIn('id', $salesRepIds)->pluck('name', 'id')->all()
            : [];

        $readReceipts = $messageIds->mapWithKeys(fn ($id) => [$id => []])->all();

        foreach ($reads as $read) {
            $name = null;
            if ($read->reader_type === 'employee') {
                $name = $employeeNames[$read->reader_id] ?? ('Employee #' . $read->reader_id);
            } elseif (in_array($read->reader_type, ['sales_rep', 'salesrep'], true)) {
                $name = $salesRepNames[$read->reader_id] ?? ('Sales Rep #' . $read->reader_id);
            } else {
                $name = $userNames[$read->reader_id] ?? ('User #' . $read->reader_id);
            }

            foreach ($messageIds as $messageId) {
                if ($read->last_read_message_id >= $messageId) {
                    $readReceipts[$messageId][] = $name;
                }
            }
        }

        foreach ($readReceipts as $messageId => $names) {
            $readReceipts[$messageId] = array_values(array_unique($names));
        }

        return $readReceipts;
    }

    private function allParticipantsReadUpTo(Project $project, array $identity): ?array
    {
        $participants = ProjectMessage::query()
            ->where('project_id', $project->id)
            ->select('author_type', 'author_id')
            ->distinct()
            ->get()
            ->filter(fn ($participant) => $participant->author_id
                && ! ($participant->author_type === $identity['type']
                    && (string) $participant->author_id === (string) $identity['id']))
            ->values();

        if ($participants->isEmpty()) {
            return null;
        }

        $reads = ProjectMessageRead::query()
            ->where('project_id', $project->id)
            ->whereNotNull('last_read_message_id')
            ->get(['reader_type', 'reader_id', 'last_read_message_id']);

        $readMap = [];
        foreach ($reads as $read) {
            $key = $read->reader_type . ':' . $read->reader_id;
            $readMap[$key] = max($readMap[$key] ?? 0, (int) $read->last_read_message_id);
        }

        $minReadId = null;
        foreach ($participants as $participant) {
            $key = $participant->author_type . ':' . $participant->author_id;
            if (! array_key_exists($key, $readMap)) {
                return null;
            }
            $minReadId = $minReadId === null
                ? $readMap[$key]
                : min($minReadId, $readMap[$key]);
        }

        if (! $minReadId) {
            return null;
        }

        $message = $project->messages()
            ->whereKey($minReadId)
            ->first();

        if (! $message) {
            return null;
        }

        return [
            'message_id' => $message->id,
            'label' => $message->created_at?->format(config('app.datetime_format', 'd-m-Y h:i A')) ?? ('#' . $message->id),
        ];
    }

}
