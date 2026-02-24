<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TaskQueryService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TasksController extends Controller
{
    public function index(Request $request, TaskQueryService $taskQueryService): View|InertiaResponse
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

        $payload = [
            'tasks' => $tasks,
            'statusFilter' => $statusFilter,
            'search' => $search,
            'statusCounts' => $taskQueryService->tasksSummaryForUser($user),
            'routePrefix' => 'admin',
            'usesStartRoute' => false,
        ];

        if ($request->header('HX-Request')) {
            return view('tasks.partials.index', $payload);
        }

        return Inertia::render(
            'Admin/Tasks/Index',
            [
                'pageTitle' => 'Tasks',
                // Preserve existing task table/actions HTML to avoid behavior drift.
                'table_html' => view('tasks.partials.index', $payload)->render(),
            ]
        );
    }
}
