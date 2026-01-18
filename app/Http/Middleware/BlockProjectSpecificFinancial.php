<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockProjectSpecificFinancial
{
    /**
     * Handle an incoming request.
     *
     * Block project-specific users from accessing financial routes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isClientProject()) {
            abort(403, 'Access denied.');
        }

        return $next($request);
    }
}
