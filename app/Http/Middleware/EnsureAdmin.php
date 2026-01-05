<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('admin.login');
        }

        if (! $user->isMasterAdmin() && ! $user->isSubAdmin() && ! $user->isSales() && ! $user->isSupport()) {
            abort(403);
        }

        return $next($request);
    }
}
