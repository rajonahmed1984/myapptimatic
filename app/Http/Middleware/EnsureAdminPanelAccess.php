<?php

namespace App\Http\Middleware;

use App\Support\AuthFresh\AdminAccess;
use App\Support\AuthFresh\Portal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminPanelAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();
        if (! $user) {
            return redirect(Portal::portalLoginUrl('admin'));
        }

        if (! AdminAccess::canAccess($user)) {
            abort(403);
        }

        return $next($request);
    }
}
