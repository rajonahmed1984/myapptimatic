<?php

namespace App\Http\Middleware;

use App\Models\SalesRepresentative;
use Illuminate\Support\Facades\Auth;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureSalesRep
{
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('sales');
        $user = Auth::guard('sales')->user();

        if (! $user) {
            return redirect()->route('sales.login');
        }

        $rep = SalesRepresentative::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (! $rep) {
            Auth::guard('sales')->logout();
            $response = Inertia::render('Rep/AccessRevoked', [
                'pageTitle' => 'Access revoked',
                'message' => 'Your sales representative access is currently inactive. If you believe this is an error, please contact an administrator.',
                'routes' => [
                    'login' => route('login', [], false),
                ],
            ])->toResponse($request);
            $response->setStatusCode(403);

            return $response;
        }

        $request->attributes->set('salesRep', $rep);

        return $next($request);
    }
}
