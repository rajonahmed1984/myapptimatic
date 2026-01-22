<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Services\TaskQueryService;
use Illuminate\Http\Request;

class TasksController extends Controller
{
    public function index(Request $request, TaskQueryService $taskQueryService)
    {
        if (! $taskQueryService->canViewTasks($request->user())) {
            abort(403);
        }

        abort(403);
    }
}
