<?php

namespace App\Http\Middleware;

use App\Services\Mail\ImapAuthService;
use App\Services\Mail\MailSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateMailSessionFreshness
{
    public function __construct(
        private readonly MailSessionService $mailSessionService,
        private readonly ImapAuthService $imapAuthService
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $session = $this->mailSessionService->validateSession($request);
        if (! $session) {
            return $this->redirectWithReloginMessage($request);
        }

        if ($this->mailSessionService->isFresh($session)) {
            return $next($request);
        }

        $password = $this->mailSessionService->decryptPassword($request);
        if (! is_string($password) || $password === '') {
            $this->mailSessionService->invalidateSession($request, $session);

            return $this->redirectWithReloginMessage($request);
        }

        $mailAccount = $session->mailAccount;
        if (! $mailAccount || ! $this->imapAuthService->verifyCredentials($mailAccount, $password)) {
            if ($mailAccount) {
                $mailAccount->forceFill([
                    'status' => 'auth_failed',
                    'last_auth_failed_at' => now(),
                ])->save();
            }

            $this->mailSessionService->invalidateSession($request, $session);

            return $this->redirectWithReloginMessage($request);
        }

        $this->mailSessionService->touchValidated($session);

        return $next($request);
    }

    private function redirectWithReloginMessage(Request $request): Response
    {
        $routeName = (string) $request->route()?->getName();
        $loginRoute = 'admin.apptimatic-email.login';

        if (str_starts_with($routeName, 'employee.')) {
            $loginRoute = 'employee.apptimatic-email.login';
        } elseif (str_starts_with($routeName, 'rep.')) {
            $loginRoute = 'rep.apptimatic-email.login';
        } elseif (str_starts_with($routeName, 'support.')) {
            $loginRoute = 'support.apptimatic-email.login';
        }

        return redirect()
            ->route($loginRoute)
            ->withErrors(['email' => 'Mailbox credentials changed or expired. Please login again.']);
    }
}
