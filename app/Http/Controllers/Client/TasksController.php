<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\TaskQueryService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TasksController extends Controller
{
    public function index(Request $request, TaskQueryService $taskQueryService): InertiaResponse|Response
    {
        $user = $request->user();
        if (! $taskQueryService->canViewTasks($user)) {
            abort(403);
        }

        $statusFilter = (string) $request->input('status', '');
        $search = trim((string) $request->input('search', ''));

        $tasksQuery = $taskQueryService->visibleTasksForUser($user)
            ->with('project')
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
            $tasksQuery->where('title', 'like', '%'.$search.'%');
        }

        $tasks = $tasksQuery->paginate(25)->withQueryString();
        $tasks->setCollection($taskQueryService->actionableTasksForUser($user, $tasks->getCollection()));

        $payload = [
            'tasks' => $tasks,
            'statusFilter' => $statusFilter,
            'search' => $search,
            'statusCounts' => $taskQueryService->tasksSummaryForUser($user),
            'routePrefix' => 'client',
            'usesStartRoute' => false,
        ];

        if ($request->header('HX-Request')) {
            return response()->view('tasks.partials.index', $payload);
        }

        return Inertia::render('Client/Tasks/Index', [
            'status_filter' => $statusFilter,
            'search' => $search,
            'status_counts' => [
                'open' => (int) ($payload['statusCounts']['open'] ?? 0),
                'in_progress' => (int) ($payload['statusCounts']['in_progress'] ?? 0),
                'completed' => (int) ($payload['statusCounts']['completed'] ?? 0),
                'total' => (int) ($payload['statusCounts']['total'] ?? 0),
            ],
            'tasks' => $tasks->getCollection()->map(function ($task) {
                $currentStatus = (string) ($task->status ?? 'pending');
                $statusLabels = [
                    'pending' => 'Open',
                    'todo' => 'Open',
                    'in_progress' => 'Inprogress',
                    'blocked' => 'Blocked',
                    'completed' => 'Completed',
                    'done' => 'Completed',
                ];

                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'created_date' => $task->created_at?->format(config('app.date_format', 'd-m-Y')) ?? '--',
                    'created_time' => $task->created_at?->format('H:i') ?? '--',
                    'status' => $currentStatus,
                    'status_label' => $statusLabels[$currentStatus] ?? ucfirst(str_replace('_', ' ', $currentStatus)),
                    'project' => $task->project ? [
                        'id' => $task->project->id,
                        'name' => $task->project->name,
                        'routes' => [
                            'show' => route('client.projects.show', $task->project),
                            'task_show' => route('client.projects.tasks.show', [$task->project, $task]),
                            'task_update' => route('client.projects.tasks.update', [$task->project, $task]),
                        ],
                    ] : null,
                    'can_start' => (bool) ($task->can_start ?? false),
                    'can_complete' => (bool) ($task->can_complete ?? false),
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
                'index' => route('client.tasks.index'),
                'projects' => route('client.projects.index'),
            ],
        ]);
    }
}
