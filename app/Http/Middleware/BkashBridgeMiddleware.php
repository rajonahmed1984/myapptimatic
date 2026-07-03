<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BkashBridgeMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('services.bkash.api_key');

        if (!$token) {
            return response()->json(['message' => 'bKash bridge API key is not configured.'], 500);
        }

        $headerToken = $request->bearerToken();
        if (!$headerToken || $headerToken !== $token) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
