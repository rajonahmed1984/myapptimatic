<?php

namespace App\Http\Middleware;

use App\Support\AuthFresh\AdminAccess;
use App\Support\AuthFresh\Portal;
use Closure;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated as Middleware;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated extends Middleware
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            $authGuard = Auth::guard($guard);
            if (! $authGuard->check()) {
                continue;
            }

            if (! $this->shouldRedirectForPortal($request, $guard, $authGuard->user())) {
                $this->trace($request, $guard, 'skip_redirect_portal_mismatch');

                continue;
            }

            $this->trace($request, $guard, 'redirect_authenticated');

            return redirect($this->redirectTo($request));
        }

        return $next($request);
    }


    private function shouldRedirectForPortal(Request $request, ?string $guard, mixed $user): bool
    {
        if ($guard !== 'web') {
            return true;
        }

        $portal = Portal::fromRequest($request);
        if ($portal === 'admin' && ! AdminAccess::canAccess($user)) {
            return false;
        }

        return true;
    }

    private function trace(Request $request, ?string $guard, string $action): void
    {
        if (! config('app.login_trace')) {
            return;
        }

        Log::info('[GUEST_TRACE]', [
            'action' => $action,
            'guard' => $guard ?? 'default',
            'path' => $request->path(),
            'method' => $request->method(),
            'has_session' => $request->hasSession(),
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'has_session_cookie' => $request->cookies->has((string) config('session.cookie')),
            'x_forwarded_proto' => $request->headers->get('x-forwarded-proto'),
            'is_secure' => $request->isSecure(),
        ]);
    }
}
