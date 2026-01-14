<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class NoCacheHeaders
{
    /**
     * Handle an incoming request to prevent browser caching of protected pages.
     * This ensures that back button navigation cannot display stale protected content.
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only apply cache headers to HTML responses
        if ($response->headers->get('content-type') && str_contains($response->headers->get('content-type'), 'text/html')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}
