<?php

namespace App\Http\Middleware;

use App\Models\SalesRepresentative;
use Illuminate\Support\Facades\View;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSalesRep
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $rep = SalesRepresentative::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (! $rep) {
            if (View::exists('rep.access-revoked')) {
                return response()->view('rep.access-revoked', [], 403);
            }

            abort(403, 'Sales representative access required.');
        }

        $request->attributes->set('salesRep', $rep);

        return $next($request);
    }
}
