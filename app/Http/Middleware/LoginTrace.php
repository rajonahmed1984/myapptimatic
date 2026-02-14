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
        $routeMiddleware = $route?->gatherMiddleware() ?? [];
        $resolvedGuard = $this->resolveGuard($routeMiddleware);
        $sessionCookieName = (string) config('session.cookie');
        $incomingSessionCookie = $sessionCookieName !== ''
            ? $request->cookie($sessionCookieName)
            : null;

        Log::info('[LOGIN_TRACE] Request', [
            'route_name' => $route?->getName(),
            'path' => $request->path(),
            'method' => $request->method(),
            'host' => $request->getHost(),
            'scheme' => $request->getScheme(),
            'is_secure' => $request->isSecure(),
            'x_forwarded_proto' => $request->headers->get('x-forwarded-proto'),
            'x_forwarded_host' => $request->headers->get('x-forwarded-host'),
            'x_forwarded_port' => $request->headers->get('x-forwarded-port'),
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'session_cookie_name' => $sessionCookieName,
            'incoming_session_cookie' => $incomingSessionCookie,
            'session_has_old_input' => $request->hasSession() ? $request->session()->hasOldInput() : null,
            'session_has_errors' => $request->hasSession() ? $request->session()->has('errors') : null,
            'route_middleware' => $routeMiddleware,
            'resolved_guard' => $resolvedGuard,
            'auth_default_check' => Auth::check(),
            'guard_checks' => $this->guardChecks(),
        ]);

        $response = $next($request);

        $sessionCookie = null;
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $sessionCookieName) {
                $sessionCookie = $cookie;
                break;
            }
        }

        Log::info('[LOGIN_TRACE] Response', [
            'route_name' => $route?->getName(),
            'status' => $response->getStatusCode(),
            'redirect_to' => $response->headers->get('Location'),
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'session_has_old_input' => $request->hasSession() ? $request->session()->hasOldInput() : null,
            'session_has_errors' => $request->hasSession() ? $request->session()->has('errors') : null,
            'set_session_cookie' => $sessionCookie !== null,
            'set_session_cookie_name' => $sessionCookie?->getName(),
            'set_session_cookie_value' => $sessionCookie?->getValue(),
            'set_session_cookie_domain' => $sessionCookie?->getDomain(),
            'set_session_cookie_secure' => $sessionCookie?->isSecure(),
            'auth_default_check' => Auth::check(),
            'guard_checks' => $this->guardChecks(),
        ]);

        return $response;
    }

    /**
     * @param  list<string>  $routeMiddleware
     */
    private function resolveGuard(array $routeMiddleware): string
    {
        foreach ($routeMiddleware as $middleware) {
            if (str_starts_with($middleware, 'guest:') || str_starts_with($middleware, 'auth:')) {
                $guards = explode(',', explode(':', $middleware, 2)[1]);
                $guard = trim((string) ($guards[0] ?? ''));
                if ($guard !== '') {
                    return $guard;
                }
            }

            if ($middleware === 'employee') {
                return 'employee';
            }

            if ($middleware === 'salesrep') {
                return 'sales';
            }

            if ($middleware === 'support') {
                return 'support';
            }

            if ($middleware === 'admin' || $middleware === 'client') {
                return 'web';
            }
        }

        return Auth::getDefaultDriver();
    }

    /**
     * @return array<string, bool>
     */
    private function guardChecks(): array
    {
        return [
            'web' => Auth::guard('web')->check(),
            'employee' => Auth::guard('employee')->check(),
            'sales' => Auth::guard('sales')->check(),
            'support' => Auth::guard('support')->check(),
        ];
    }
}
