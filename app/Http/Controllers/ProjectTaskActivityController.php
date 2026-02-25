<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskActivity;
use App\Models\SalesRepresentative;
use App\Http\Requests\StoreTaskActivityRequest;
use App\Support\TaskActivityLogger;
use App\Support\TaskSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProjectTaskActivityController extends Controller
{
    public function index(Request $request, Project $project, ProjectTask $task): JsonResponse|Response
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $task);

        $activityPaginator = $task->activities()
            ->with(['userActor', 'employeeActor', 'salesRepActor'])
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString();

        $activities = $activityPaginator->getCollection()->reverse()->values();

        $identity = $this->resolveActorIdentity($request);
        $routePrefix = $this->resolveRoutePrefix($request);
        $attachmentRouteName = $routePrefix . '.projects.tasks.activity.attachment';

        if ($request->boolean('partial')) {
            return response()->view('projects.partials.task-activity-feed', [
                'activities' => $activities,
                'project' => $project,
                'task' => $task,
                'attachmentRouteName' => $attachmentRouteName,
                'currentActorType' => $identity['type'],
                'currentActorId' => $identity['id'],
            ]);
        }

        return response()->json($activityPaginator->items());
    }

    public function items(Request $request, Project $project, ProjectTask $task): JsonResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $task);

        $limit = min(max((int) $request->query('limit', 30), 1), 100);
        $afterId = (int) $request->query('after_id', 0);
        $beforeId = (int) $request->query('before_id', 0);

        $query = $task->activities()
            ->with(['userActor', 'employeeActor', 'salesRepActor']);

        if ($afterId > 0) {
            $query->where('id', '>', $afterId)->orderBy('id');
        } elseif ($beforeId > 0) {
            $query->where('id', '<', $beforeId)->orderByDesc('id');
        } else {
            $query->orderByDesc('id');
        }

        $activities = $query->limit($limit)->get();
        if ($afterId <= 0) {
            $activities = $activities->reverse()->values();
        }

        $identity = $this->resolveActorIdentity($request);
        $routePrefix = $this->resolveRoutePrefix($request);
        $attachmentRouteName = $routePrefix . '.projects.tasks.activity.attachment';

        $items = $activities->map(fn (ProjectTaskActivity $activity) => $this->activityItem(
            $activity,
            $project,
            $task,
            $attachmentRouteName,
            $identity
        ))->values();

        $nextAfterId = $activities->max('id') ?? $afterId;
        $nextBeforeId = $activities->min('id') ?? $beforeId;

        return response()->json([
            'ok' => true,
            'data' => [
                'items' => $items,
                'next_after_id' => $nextAfterId,
                'next_before_id' => $nextBeforeId,
            ],
        ]);
    }

    public function store(StoreTaskActivityRequest $request, Project $project, ProjectTask $task): RedirectResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('comment', $task);

        $data = $request->validated();

        $message = trim((string) $data['message']);
        if ($message === '') {
            return back()->withErrors(['message' => 'Message cannot be empty.']);
        }

        TaskActivityLogger::record($task, $request, 'comment', $message);

        $urls = $this->extractUrls($message);
        foreach ($urls as $url) {
            TaskActivityLogger::record($task, $request, 'link', null, [
                'url' => $url,
                'host' => parse_url($url, PHP_URL_HOST),
            ]);
        }

        return back()->with('status', 'Comment added.');
    }

    public function storeItem(StoreTaskActivityRequest $request, Project $project, ProjectTask $task): JsonResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('comment', $task);

        $data = $request->validated();
        $message = trim((string) $data['message']);
        if ($message === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Message cannot be empty.',
            ], 422);
        }

        $activities = [];
        $activities[] = TaskActivityLogger::record($task, $request, 'comment', $message);

        $urls = $this->extractUrls($message);
        foreach ($urls as $url) {
            $activities[] = TaskActivityLogger::record($task, $request, 'link', null, [
                'url' => $url,
                'host' => parse_url($url, PHP_URL_HOST),
            ]);
        }

        collect($activities)->each->load(['userActor', 'employeeActor', 'salesRepActor']);

        $identity = $this->resolveActorIdentity($request);
        $routePrefix = $this->resolveRoutePrefix($request);
        $attachmentRouteName = $routePrefix . '.projects.tasks.activity.attachment';

        $items = collect($activities)
            ->filter()
            ->map(fn (ProjectTaskActivity $activity) => $this->activityItem(
                $activity,
                $project,
                $task,
                $attachmentRouteName,
                $identity
            ))
            ->values();

        $maxId = collect($activities)->max('id') ?? 0;
        $minId = collect($activities)->min('id') ?? 0;

        return response()->json([
            'ok' => true,
            'message' => 'Comment added.',
            'data' => [
                'items' => $items,
                'next_after_id' => $maxId,
                'next_before_id' => $minId,
            ],
        ]);
    }

    public function upload(Request $request, Project $project, ProjectTask $task): RedirectResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('upload', $task);

        $maxMb = TaskSettings::uploadMaxMb();

        $data = $request->validate([
            'attachments' => ['array', 'min:1', 'required_without:attachment'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,docx,xlsx', 'max:' . ($maxMb * 1024)],
            'attachment' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,docx,xlsx', 'max:' . ($maxMb * 1024), 'required_without:attachments'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $files = $request->file('attachments', []);
        if (empty($files) && $request->hasFile('attachment')) {
            $files = [$request->file('attachment')];
        }

        $message = isset($data['message']) ? trim((string) $data['message']) : null;
        $message = $message === '' ? null : $message;

        foreach ($files as $file) {
            $attachmentPath = $this->storeAttachment($file, $task);
            TaskActivityLogger::record($task, $request, 'upload', $message, [], $attachmentPath);
        }

        return back()->with('status', count($files) > 1 ? 'Files uploaded.' : 'File uploaded.');
    }

    public function attachment(Request $request, Project $project, ProjectTask $task, ProjectTaskActivity $activity)
    {
        $this->ensureTaskBelongsToProject($project, $task);
        if ($activity->project_task_id !== $task->id) {
            abort(404);
        }

        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $task);

        if (! $activity->attachment_path) {
            abort(404, 'This activity does not have an attachment.');
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($activity->attachment_path)) {
            Log::warning('Attachment file not found in storage', [
                'activity_id' => $activity->id,
                'attachment_path' => $activity->attachment_path,
                'task_id' => $task->id,
                'project_id' => $project->id
            ]);
            abort(404, 'The attachment file is no longer available. It may have been deleted or the upload was interrupted.');
        }

        if ($activity->isImageAttachment()) {
            return $disk->response($activity->attachment_path);
        }

        return $disk->download($activity->attachment_path, $activity->attachmentName() ?? 'attachment');
    }

    private function ensureTaskBelongsToProject(Project $project, ProjectTask $task): void
    {
        if ($task->project_id !== $project->id) {
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

    private function resolveActorIdentity(Request $request): array
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
        if ($user?->isAdmin()) {
            return ['type' => 'admin', 'id' => $user?->id];
        }

        return ['type' => 'client', 'id' => $user?->id];
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

    private function storeAttachment(UploadedFile $file, ProjectTask $task): string
    {
        $name = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
        $name = $name !== '' ? Str::slug($name) : 'attachment';
        $extension = $file->getClientOriginalExtension();
        $fileName = $name . '-' . Str::random(8) . '.' . $extension;

        return $file->storeAs('project-task-activities/' . $task->id, $fileName, 'public');
    }

    private function extractUrls(string $text): array
    {
        preg_match_all('~https?://[^\s<]+~i', $text, $matches);
        $urls = $matches[0] ?? [];
        $unique = array_values(array_unique($urls));

        return $unique;
    }

    private function activityItem(
        ProjectTaskActivity $activity,
        Project $project,
        ProjectTask $task,
        string $attachmentRouteName,
        array $identity
    ): array {
        return [
            'id' => $activity->id,
            'html' => view('projects.partials.task-activity-item', [
                'activity' => $activity,
                'project' => $project,
                'task' => $task,
                'attachmentRouteName' => $attachmentRouteName,
                'currentActorType' => $identity['type'],
                'currentActorId' => $identity['id'],
            ])->render(),
        ];
    }
}
