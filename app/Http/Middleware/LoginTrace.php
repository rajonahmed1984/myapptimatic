<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LoginTrace
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.login_trace')) {
            return $next($request);
        }

        $route = $request->route();
        $sessionCookieName = (string) config('session.cookie');
        $sessionIdBefore = $request->hasSession() ? $request->session()->getId() : null;
        $requestHasSessionCookie = $sessionCookieName !== '' && $request->cookies->has($sessionCookieName);

        Log::info('[LOGIN_TRACE] RouteProbeBefore', [
            'route_name' => $route?->getName(),
            'path' => $request->path(),
            'method' => $request->method(),
            'host' => $request->getHost(),
            'scheme' => $request->getScheme(),
            'is_secure' => $request->isSecure(),
            'x_forwarded_proto' => $request->headers->get('x-forwarded-proto'),
            'x_forwarded_for' => $request->headers->get('x-forwarded-for'),
            'x_forwarded_host' => $request->headers->get('x-forwarded-host'),
            'session_cookie_name' => $sessionCookieName,
            'request_has_session_cookie' => $requestHasSessionCookie,
            'session_id' => $sessionIdBefore,
            'csrf_token_present' => $request->has('_token'),
            'guard_checks' => $this->guardChecks(),
        ]);

        $response = $next($request);

        $sessionIdAfter = $request->hasSession() ? $request->session()->getId() : null;

        Log::info('[LOGIN_TRACE] RouteProbeAfter', [
            'route_name' => $route?->getName(),
            'path' => $request->path(),
            'method' => $request->method(),
            'status' => $response->getStatusCode(),
            'host' => $request->getHost(),
            'scheme' => $request->getScheme(),
            'is_secure' => $request->isSecure(),
            'x_forwarded_proto' => $request->headers->get('x-forwarded-proto'),
            'x_forwarded_for' => $request->headers->get('x-forwarded-for'),
            'x_forwarded_host' => $request->headers->get('x-forwarded-host'),
            'session_cookie_name' => $sessionCookieName,
            'request_has_session_cookie' => $requestHasSessionCookie,
            'session_id_before' => $sessionIdBefore,
            'session_id_after' => $sessionIdAfter,
            'csrf_token_present' => $request->has('_token'),
            'guard_checks' => $this->guardChecks(),
            'redirect_to' => $response->headers->get('Location'),
        ]);

        return $response;
    }

    /**
     * @return array<string, bool>
     */
    private function guardChecks(): array
    {
        $webUser = Auth::guard('web')->user();

        return [
            'web' => Auth::guard('web')->check(),
            'admin' => $webUser !== null && method_exists($webUser, 'isAdmin') && $webUser->isAdmin(),
            'employee' => Auth::guard('employee')->check(),
            'sales' => Auth::guard('sales')->check(),
            'support' => Auth::guard('support')->check(),
        ];
    }
}
