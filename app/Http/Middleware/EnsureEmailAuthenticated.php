<?php

namespace App\Http\Middleware;

use App\Services\Mail\MailSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailAuthenticated
{
    public function __construct(private readonly MailSessionService $mailSessionService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $mailSession = $this->mailSessionService->validateSession($request);
        if ($mailSession) {
            return $next($request);
        }

        $routeName = (string) $request->route()?->getName();
        $loginRoute = $this->resolveLoginRoute($routeName);

        return redirect()
            ->route($loginRoute)
            ->withErrors(['email' => 'Please login to Apptimatic Email first.']);
    }

    private function resolveLoginRoute(string $routeName): string
    {
        if (str_starts_with($routeName, 'employee.')) {
            return 'employee.apptimatic-email.login';
        }

        if (str_starts_with($routeName, 'rep.')) {
            return 'rep.apptimatic-email.login';
        }

        if (str_starts_with($routeName, 'support.')) {
            return 'support.apptimatic-email.login';
        }

        return 'admin.apptimatic-email.login';
    }
}
