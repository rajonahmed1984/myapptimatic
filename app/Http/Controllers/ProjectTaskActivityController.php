<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskActivity;
use App\Models\SalesRepresentative;
use App\Support\TaskActivityLogger;
use App\Support\TaskSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProjectTaskActivityController extends Controller
{
    public function index(Request $request, Project $project, ProjectTask $task)
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('view', $task);

        $activities = $task->activities()
            ->with(['userActor', 'employeeActor', 'salesRepActor'])
            ->orderBy('created_at')
            ->get();

        $identity = $this->resolveActorIdentity($request);
        $routePrefix = $this->resolveRoutePrefix($request);
        $attachmentRouteName = $routePrefix . '.projects.tasks.activity.attachment';

        if ($request->boolean('partial')) {
            return view('projects.partials.task-activity-feed', [
                'activities' => $activities,
                'project' => $project,
                'task' => $task,
                'attachmentRouteName' => $attachmentRouteName,
                'currentActorType' => $identity['type'],
                'currentActorId' => $identity['id'],
            ]);
        }

        return response()->json($activities);
    }

    public function store(Request $request, Project $project, ProjectTask $task): RedirectResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('comment', $task);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

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

    public function upload(Request $request, Project $project, ProjectTask $task): RedirectResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $actor = $this->resolveActor($request);
        Gate::forUser($actor)->authorize('upload', $task);

        $maxMb = TaskSettings::uploadMaxMb();

        $data = $request->validate([
            'attachment' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf,docx,xlsx', 'max:' . ($maxMb * 1024)],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $attachmentPath = $this->storeAttachment($request, $task);
        $message = isset($data['message']) ? trim((string) $data['message']) : null;
        $message = $message === '' ? null : $message;

        TaskActivityLogger::record($task, $request, 'upload', $message, [], $attachmentPath);

        return back()->with('status', 'File uploaded.');
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
            abort(404);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($activity->attachment_path)) {
            abort(404);
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

    private function storeAttachment(Request $request, ProjectTask $task): string
    {
        $file = $request->file('attachment');
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
}
