<?php

namespace App\Http\Controllers\SalesRep;

use App\Http\Controllers\Controller;
use App\Services\TaskQueryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TasksController extends Controller
{
    public function index(Request $request, TaskQueryService $taskQueryService): View
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
            $tasksQuery->where('title', 'like', '%' . $search . '%');
        }

        $tasks = $tasksQuery->paginate(25)->withQueryString();
        $tasks->setCollection($taskQueryService->actionableTasksForUser($user, $tasks->getCollection()));

        return view('rep.tasks.index', [
            'tasks' => $tasks,
            'statusFilter' => $statusFilter,
            'search' => $search,
            'statusCounts' => $taskQueryService->tasksSummaryForUser($user),
            'routePrefix' => 'rep',
            'usesStartRoute' => false,
        ]);
    }
}
