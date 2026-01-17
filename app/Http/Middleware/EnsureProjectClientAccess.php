<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProjectClientAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isClientProject()) {
            return $next($request);
        }

        $project = $request->route('project');

        if (! $project) {
            return $next($request);
        }

        $projectId = $project instanceof Project ? $project->id : (int) $project;

        if (! $projectId || $projectId !== $user->project_id) {
            abort(403);
        }

        return $next($request);
    }
}
