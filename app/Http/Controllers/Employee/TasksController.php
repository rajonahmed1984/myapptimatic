<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\TaskQueryService;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TasksController extends Controller
{
    public function index(Request $request, TaskQueryService $taskQueryService): Response|InertiaResponse
    {
        $user = $request->user();
        if (! $taskQueryService->canViewTasks($user)) {
            abort(403);
        }

        $statusFilter = (string) $request->input('status', '');
        $search = trim((string) $request->input('search', ''));

        $tasksQuery = $taskQueryService->visibleTasksForUser($user)
            ->with('project')
            ->withCount([
                'subtasks',
                'subtasks as completed_subtasks_count' => fn ($query) => $query->where('is_completed', true),
            ])
            ->orderByDesc('created_at');

        if ($statusFilter === 'open') {
            $tasksQuery->whereIn('status', ['pending', 'todo', 'blocked']);
        } elseif ($statusFilter === 'in_progress') {
            $tasksQuery->where('status', 'in_progress');
        } elseif ($statusFilter === 'completed') {
            $tasksQuery->whereIn('status', ['completed', 'done']);
        }

        if ($search !== '') {
            $tasksQuery->where('title', 'like', '%'.$search.'%');
        }

        $tasks = $tasksQuery->paginate(25)->withQueryString();
        $tasks->setCollection($taskQueryService->actionableTasksForUser($user, $tasks->getCollection()));

        $payload = [
            'tasks' => $tasks,
            'statusFilter' => $statusFilter,
            'search' => $search,
            'statusCounts' => $taskQueryService->tasksSummaryForUser($user),
            'routePrefix' => 'employee',
            'usesStartRoute' => true,
        ];

        if ($request->header('HX-Request')) {
            return response()->view('tasks.partials.index', $payload);
        }

        return Inertia::render('Employee/Tasks/Index', [
            'status_filter' => $statusFilter,
            'search' => $search,
            'status_counts' => $payload['statusCounts'],
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
                    'completed_subtasks_count' => (int) ($task->completed_subtasks_count ?? 0),
                    'can_start' => (bool) ($task->can_start ?? false),
                    'can_complete' => (bool) ($task->can_complete ?? false),
                    'project' => $task->project ? [
                        'id' => $task->project->id,
                        'name' => $task->project->name,
                    ] : null,
                    'routes' => $task->project ? [
                        'project_show' => route('employee.projects.show', $task->project),
                        'task_show' => route('employee.projects.tasks.show', [$task->project, $task]),
                        'task_update' => route('employee.projects.tasks.update', [$task->project, $task]),
                        'task_start' => route('employee.projects.tasks.start', [$task->project, $task]),
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
                'index' => route('employee.tasks.index'),
                'projects_index' => route('employee.projects.index'),
            ],
        ]);
    }
}
