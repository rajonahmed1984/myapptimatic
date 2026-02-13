<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    /**
     * Ensure the authenticated admin has one of the allowed roles.
     *
     * Usage: ->middleware('admin.role:master_admin,sub_admin')
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = Auth::guard('web')->user();

        if (! $user) {
            return redirect()->route('admin.login');
        }

        // Roles may arrive as a single comma-separated string or multiple args.
        $allowedRoles = collect($roles)
            ->flatMap(fn ($role) => explode(',', (string) $role))
            ->map(fn ($role) => trim($role))
            ->filter()
            ->all();

        if (empty($allowedRoles)) {
            abort(403);
        }

        if (! in_array($user->role, $allowedRoles, true)) {
            // Authenticated but unauthorized admin role should receive 403.
            abort(403);
        }

        return $next($request);
    }
}
