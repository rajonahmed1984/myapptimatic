<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskMessage;
use App\Models\ProjectTaskMessageRead;
use App\Models\SalesRepresentative;
use App\Http\Requests\StoreTaskChatMessageRequest;
use App\Services\ChatAiService;
use App\Services\GeminiService;
use App\Services\ChatAiSummaryCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProjectTaskChatController extends Controller
{
    public function show(Request $request, Project $project, ProjectTask $task, ChatAiSummaryCache $summaryCache)
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $task);

        $routePrefix = $this->resolveRoutePrefix($request);
        $messages = $task->messages()
            ->with(['userAuthor', 'employeeAuthor', 'salesRepAuthor'])
            ->latest('id')
            ->limit(30)
            ->get()
            ->reverse()
            ->values();

        $identity = $this->resolveAuthorIdentity($request);
        $attachmentRouteName = $routePrefix . '.projects.tasks.messages.attachment';

        $canPost = Gate::forUser($actor)->check('comment', $task);

        if ($request->boolean('partial')) {
            return view('projects.partials.task-chat-messages', [
                'messages' => $messages,
                'project' => $project,
                'task' => $task,
                'attachmentRouteName' => $attachmentRouteName,
                'currentAuthorType' => $identity['type'],
                'currentAuthorId' => $identity['id'],
            ]);
        }

        return view('projects.task-chat', [
            'layout' => $this->layoutForPrefix($routePrefix),
            'project' => $project,
            'task' => $task,
            'messages' => $messages,
            'postRoute' => route($routePrefix . '.projects.tasks.chat.store', [$project, $task]),
            'backRoute' => route($routePrefix . '.projects.show', $project),
            'attachmentRouteName' => $attachmentRouteName,
            'messagesUrl' => route($routePrefix . '.projects.tasks.chat.messages', [$project, $task]),
            'postMessagesUrl' => route($routePrefix . '.projects.tasks.chat.messages.store', [$project, $task]),
            'readUrl' => route($routePrefix . '.projects.tasks.chat.read', [$project, $task]),
            'aiSummaryRoute' => route($routePrefix . '.projects.tasks.chat.ai', [$project, $task]),
            'aiReady' => (bool) config('google_ai.api_key'),
            'pinnedSummary' => $summaryCache->getTask($task->id) ?? [],
            'currentAuthorType' => $identity['type'],
            'currentAuthorId' => $identity['id'],
            'canPost' => $canPost,
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
        $attachmentRouteName = $routePrefix . '.projects.tasks.messages.attachment';

        $items = $messages->map(fn (ProjectTaskMessage $message) => $this->messageItem(
            $message,
            $project,
            $task,
            $attachmentRouteName,
            $identity
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

        $message = isset($data['message']) ? trim((string) $data['message']) : null;
        $message = $message === '' ? null : $message;

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
        ]);

        return back()->with('status', 'Message sent.');
    }

    public function storeMessage(StoreTaskChatMessageRequest $request, Project $project, ProjectTask $task): JsonResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('comment', $task);

        $data = $request->validated();
        $message = isset($data['message']) ? trim((string) $data['message']) : null;
        $message = $message === '' ? null : $message;

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
        ]);
        $messageModel->load(['userAuthor', 'employeeAuthor', 'salesRepAuthor']);

        $routePrefix = $this->resolveRoutePrefix($request);
        $attachmentRouteName = $routePrefix . '.projects.tasks.messages.attachment';
        $item = $this->messageItem($messageModel, $project, $task, $attachmentRouteName, $identity);

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

    private function layoutForPrefix(string $prefix): string
    {
        return match ($prefix) {
            'client' => 'layouts.client',
            'rep' => 'layouts.rep',
            default => 'layouts.admin',
        };
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

        return $file->storeAs('project-task-messages/' . $task->id, $fileName, 'public');
    }

    private function messageItem(
        ProjectTaskMessage $message,
        Project $project,
        ProjectTask $task,
        string $attachmentRouteName,
        array $identity
    ): array {
        return [
            'id' => $message->id,
            'html' => view('projects.partials.task-chat-message', [
                'message' => $message,
                'project' => $project,
                'task' => $task,
                'attachmentRouteName' => $attachmentRouteName,
                'currentAuthorType' => $identity['type'],
                'currentAuthorId' => $identity['id'],
            ])->render(),
        ];
    }
}
