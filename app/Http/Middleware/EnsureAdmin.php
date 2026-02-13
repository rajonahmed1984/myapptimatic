<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Enums\Role;
use App\Support\SystemLogger;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if (! $user) {
            $sessionCookieName = config('session.cookie');
            $sessionCookieValue = is_string($sessionCookieName) ? $request->cookie($sessionCookieName) : null;

            SystemLogger::write('admin', 'Admin middleware denied: unauthenticated web guard.', [
                'path' => $request->path(),
                'session_id' => $request->hasSession() ? $request->session()->getId() : null,
                'session_driver' => config('session.driver'),
                'session_domain' => config('session.domain'),
                'app_url' => config('app.url'),
                'app_key_sha1' => sha1((string) config('app.key')),
                'session_cookie_present' => is_string($sessionCookieValue) && $sessionCookieValue !== '',
                'session_cookie_id' => $sessionCookieValue,
                'session_cookie_matches_session_id' => $request->hasSession() && is_string($sessionCookieValue)
                    ? $sessionCookieValue === $request->session()->getId()
                    : null,
                'session_cookie_sha1' => is_string($sessionCookieValue) ? sha1($sessionCookieValue) : null,
            ], null, $request->ip(), 'warning');

            return redirect()->route('admin.login');
        }

        if (! in_array($user->role, Role::adminPanelRoles(), true)) {
            // Authenticated users without admin role should receive 403, not login redirect.
            abort(403);
        }

        return $next($request);
    }
}
