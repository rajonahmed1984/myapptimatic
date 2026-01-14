<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSupport
{
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('support');
        $user = Auth::guard('support')->user();

        if (! $user) {
            return redirect()->route('support.login');
        }

        if ($user->role !== Role::SUPPORT) {
            Auth::guard('support')->logout();
            abort(403, 'Support access is restricted.');
        }

        return $next($request);
    }
}
