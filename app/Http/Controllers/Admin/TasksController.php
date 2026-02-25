<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TaskQueryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TasksController extends Controller
{
    public function index(Request $request, TaskQueryService $taskQueryService): InertiaResponse
    {
        $user = $request->user();
        if (! $taskQueryService->canViewTasks($user)) {
            abort(403);
        }

        $statusFilter = (string) $request->input('status', '');
        $search = trim((string) $request->input('search', ''));

        $tasksQuery = $taskQueryService->visibleTasksForUser($user)
            ->with(['project', 'createdBy'])
            ->withCount('subtasks')
            ->orderByDesc('created_at');

        if ($statusFilter === 'open') {
            $tasksQuery->whereIn('status', ['pending', 'todo', 'blocked']);
        } elseif ($statusFilter === 'in_progress') {
            $tasksQuery->where('status', 'in_progress');
        } elseif ($statusFilter === 'completed') {
            $tasksQuery->whereIn('status', ['completed', 'done']);
        }

        if ($search !== '') {
            $tasksQuery->where('title', 'like', '%' . $search . '%');
        }

        $tasks = $tasksQuery->paginate(25)->withQueryString();
        $tasks->setCollection($taskQueryService->actionableTasksForUser($user, $tasks->getCollection()));

        $statusCounts = $taskQueryService->tasksSummaryForUser($user);

        return Inertia::render(
            'Admin/Tasks/Index',
            [
                'pageTitle' => 'Tasks',
                'status_filter' => $statusFilter,
                'search' => $search,
                'status_counts' => [
                    'open' => (int) ($statusCounts['open'] ?? 0),
                    'in_progress' => (int) ($statusCounts['in_progress'] ?? 0),
                    'completed' => (int) ($statusCounts['completed'] ?? 0),
                    'total' => (int) ($statusCounts['total'] ?? 0),
                ],
                'tasks' => $tasks->getCollection()->map(function ($task) {
                    $status = (string) ($task->status ?? 'pending');

                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'status' => $status,
                        'created_at_date' => $task->created_at?->format(config('app.date_format', 'd-m-Y')),
                        'created_at_time' => $task->created_at?->format(config('app.time_format', 'h:i A')),
                        'subtasks_count' => (int) ($task->subtasks_count ?? 0),
                        'can_start' => (bool) ($task->can_start ?? false),
                        'can_complete' => (bool) ($task->can_complete ?? false),
                        'project' => $task->project ? [
                            'id' => $task->project->id,
                            'name' => $task->project->name,
                        ] : null,
                        'creator_name' => $task->createdBy?->name ?? 'System',
                        'routes' => $task->project ? [
                            'project_show' => route('admin.projects.show', $task->project),
                            'task_show' => route('admin.projects.tasks.show', [$task->project, $task]),
                            'task_update' => route('admin.projects.tasks.update', [$task->project, $task]),
                        ] : null,
                    ];
                })->values()->all(),
                'pagination' => [
                    'current_page' => $tasks->currentPage(),
                    'last_page' => $tasks->lastPage(),
                    'per_page' => $tasks->perPage(),
                    'total' => $tasks->total(),
                    'from' => $tasks->firstItem(),
                    'to' => $tasks->lastItem(),
                    'prev_page_url' => $tasks->previousPageUrl(),
                    'next_page_url' => $tasks->nextPageUrl(),
                ],
                'routes' => [
                    'index' => route('admin.tasks.index'),
                    'projects' => route('admin.projects.index'),
                ],
            ]
        );
    }
}
