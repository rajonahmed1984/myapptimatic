<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            'payments/sslcommerz/*',
            'payments/bkash/*',
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'client' => \App\Http\Middleware\EnsureClient::class,
            'client.block' => \App\Http\Middleware\PreventBlockedClientAccess::class,
            'client.notice' => \App\Http\Middleware\ShareClientInvoiceStatus::class,
            'verify.api.signature' => \App\Http\Middleware\VerifyApiSignature::class,
            'restrict.cron' => \App\Http\Middleware\RestrictCronAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (TokenMismatchException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Session expired. Please log in again.'], 419);
            }

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            auth()->logout();

            $loginRoute = $request->is('admin/*') ? 'admin.login' : 'login';

            return redirect()
                ->route($loginRoute)
                ->with('status', 'Session expired. Please log in again.');
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if ($exception->getStatusCode() !== 419) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Session expired. Please log in again.'], 419);
            }

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            auth()->logout();

            $loginRoute = $request->is('admin/*') ? 'admin.login' : 'login';

            return redirect()
                ->route($loginRoute)
                ->with('status', 'Session expired. Please log in again.');
        });
    })->create();
