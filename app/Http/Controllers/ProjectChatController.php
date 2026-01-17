<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\ProjectMessageRead;
use App\Models\UserSession;
use App\Models\SalesRepresentative;
use App\Models\User;
use App\Http\Requests\StoreTaskChatMessageRequest;
use App\Support\ChatPresence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProjectChatController extends Controller
{
    public function show(Request $request, Project $project)
    {
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $project);
        $this->touchPresence($request);

        $latestMessageId = (int) ($project->messages()->max('id') ?? 0);
        $routePrefix = $this->resolveRoutePrefix($request);
        $messages = $project->messages()
            ->with(['userAuthor', 'employeeAuthor', 'salesRepAuthor'])
            ->latest('id')
            ->limit(30)
            ->get()
            ->reverse()
            ->values();

        $identity = $this->resolveAuthorIdentity($request);
        $attachmentRouteName = $routePrefix . '.projects.chat.messages.attachment';
        $readReceipts = $this->readReceiptsForMessages($project, $messages, $identity);
        $authorStatuses = ChatPresence::authorStatusesForMessages($messages);
        $allParticipantsReadUpTo = $latestMessageId > 0
            ? $this->allParticipantsReadUpTo($project, $identity)
            : null;

        $canPost = Gate::forUser($actor)->check('view', $project);

        if ($request->boolean('partial')) {
            return view('projects.partials.project-chat-messages', [
                'messages' => $messages,
                'project' => $project,
                'attachmentRouteName' => $attachmentRouteName,
                'currentAuthorType' => $identity['type'],
                'currentAuthorId' => $identity['id'],
                'readReceipts' => $readReceipts,
                'authorStatuses' => $authorStatuses,
                'latestMessageId' => $latestMessageId,
                'allParticipantsReadUpTo' => $allParticipantsReadUpTo,
            ]);
        }

        return view('projects.project-chat', [
            'layout' => $this->layoutForPrefix($routePrefix),
            'project' => $project,
            'messages' => $messages,
            'postRoute' => route($routePrefix . '.projects.chat.store', $project),
            'backRoute' => route($routePrefix . '.projects.show', $project),
            'attachmentRouteName' => $attachmentRouteName,
            'messagesUrl' => route($routePrefix . '.projects.chat.messages', $project),
            'postMessagesUrl' => route($routePrefix . '.projects.chat.messages.store', $project),
            'readUrl' => route($routePrefix . '.projects.chat.read', $project),
            'currentAuthorType' => $identity['type'],
            'currentAuthorId' => $identity['id'],
            'canPost' => $canPost,
            'readReceipts' => $readReceipts,
            'authorStatuses' => $authorStatuses,
            'latestMessageId' => $latestMessageId,
            'allParticipantsReadUpTo' => $allParticipantsReadUpTo,
        ]);
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
            ->with(['userAuthor', 'employeeAuthor', 'salesRepAuthor']);

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
        $readReceipts = $this->readReceiptsForMessages($project, $messages, $identity);
        $authorStatuses = ChatPresence::authorStatusesForMessages($messages);
        $latestMessageId = (int) ($project->messages()->max('id') ?? 0);
        $allParticipantsReadUpTo = $latestMessageId > 0
            ? $this->allParticipantsReadUpTo($project, $identity)
            : null;

        $items = $messages->map(fn (ProjectMessage $message) => $this->messageItem(
            $message,
            $project,
            $attachmentRouteName,
            $identity,
            $readReceipts[$message->id] ?? [],
            $authorStatuses[$message->author_type . ':' . $message->author_id] ?? 'offline',
            $latestMessageId,
            $allParticipantsReadUpTo
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

    public function store(StoreTaskChatMessageRequest $request, Project $project): RedirectResponse
    {
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $project);
        $this->touchPresence($request);

        $data = $request->validated();

        $message = isset($data['message']) ? trim((string) $data['message']) : null;
        $message = $message === '' ? null : $message;

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

        ProjectMessage::create([
            'project_id' => $project->id,
            'author_type' => $identity['type'],
            'author_id' => $identity['id'],
            'message' => $message,
            'attachment_path' => $attachmentPath,
        ]);

        return back()->with('status', 'Message sent.');
    }

    public function storeMessage(StoreTaskChatMessageRequest $request, Project $project): JsonResponse
    {
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $project);
        $this->touchPresence($request);

        $data = $request->validated();
        $message = isset($data['message']) ? trim((string) $data['message']) : null;
        $message = $message === '' ? null : $message;

        if (! $request->hasFile('attachment')) {
            $duplicate = $this->findRecentDuplicate($project, $request, $message);
            if ($duplicate) {
                $duplicate->load(['userAuthor', 'employeeAuthor', 'salesRepAuthor']);
                $identity = $this->resolveAuthorIdentity($request);
                $routePrefix = $this->resolveRoutePrefix($request);
                $attachmentRouteName = $routePrefix . '.projects.chat.messages.attachment';
                $readReceipts = $this->readReceiptsForMessages($project, collect([$duplicate]), $identity);
                $authorStatuses = ChatPresence::authorStatusesForMessages(collect([$duplicate]));
                $allParticipantsReadUpTo = $this->allParticipantsReadUpTo($project, $identity);
                $item = $this->messageItem(
                    $duplicate,
                    $project,
                    $attachmentRouteName,
                    $identity,
                    $readReceipts[$duplicate->id] ?? [],
                    $authorStatuses[$duplicate->author_type . ':' . $duplicate->author_id] ?? 'offline',
                    $duplicate->id,
                    $allParticipantsReadUpTo
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

        $messageModel = ProjectMessage::create([
            'project_id' => $project->id,
            'author_type' => $identity['type'],
            'author_id' => $identity['id'],
            'message' => $message,
            'attachment_path' => $attachmentPath,
        ]);
        $messageModel->load(['userAuthor', 'employeeAuthor', 'salesRepAuthor']);

        $routePrefix = $this->resolveRoutePrefix($request);
        $attachmentRouteName = $routePrefix . '.projects.chat.messages.attachment';
        $readReceipts = $this->readReceiptsForMessages($project, collect([$messageModel]), $identity);
        $authorStatuses = ChatPresence::authorStatusesForMessages(collect([$messageModel]));
        $allParticipantsReadUpTo = $this->allParticipantsReadUpTo($project, $identity);
        $item = $this->messageItem(
            $messageModel,
            $project,
            $attachmentRouteName,
            $identity,
            $readReceipts[$messageModel->id] ?? [],
            $authorStatuses[$messageModel->author_type . ':' . $messageModel->author_id] ?? 'offline',
            $messageModel->id,
            $allParticipantsReadUpTo
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

        UserSession::query()
            ->where('user_type', get_class($user))
            ->where('user_id', $user->id)
            ->whereNull('logout_at')
            ->orderByDesc('login_at')
            ->limit(1)
            ->update(['last_seen_at' => now()]);
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

    private function layoutForPrefix(string $prefix): string
    {
        return match ($prefix) {
            'client' => 'layouts.client',
            'rep' => 'layouts.rep',
            default => 'layouts.admin',
        };
    }

    private function storeAttachment(Request $request, Project $project): ?string
    {
        if (! $request->hasFile('attachment')) {
            return null;
        }

        $file = $request->file('attachment');
        $name = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
        $name = $name !== '' ? Str::slug($name) : 'attachment';
        $extension = $file->getClientOriginalExtension();
        $fileName = $name . '-' . Str::random(8) . '.' . $extension;

        return $file->storeAs('project-messages/' . $project->id, $fileName, 'public');
    }

    private function messageItem(
        ProjectMessage $message,
        Project $project,
        string $attachmentRouteName,
        array $identity,
        array $seenBy,
        string $authorStatus,
        int $latestMessageId,
        ?array $allParticipantsReadUpTo
    ): array {
        return [
            'id' => $message->id,
            'html' => view('projects.partials.project-chat-message', [
                'message' => $message,
                'project' => $project,
                'attachmentRouteName' => $attachmentRouteName,
                'currentAuthorType' => $identity['type'],
                'currentAuthorId' => $identity['id'],
                'seenBy' => $seenBy,
                'authorStatus' => $authorStatus,
                'latestMessageId' => $latestMessageId,
                'allParticipantsReadUpTo' => $allParticipantsReadUpTo,
            ])->render(),
        ];
    }

    private function readReceiptsForMessages(Project $project, $messages, array $identity): array
    {
        $messageIds = $messages->pluck('id')->filter()->values();
        if ($messageIds->isEmpty()) {
            return [];
        }

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
            'label' => $message->created_at?->format('M d, Y H:i') ?? ('#' . $message->id),
        ];
    }
}
