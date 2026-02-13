<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use App\Providers\AuthServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\ActivityTrackingEventServiceProvider;
use App\Providers\AppServiceProvider;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withProviders([
        AppServiceProvider::class,
        AuthServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        ActivityTrackingEventServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');

        // Use active guard + role to prevent cross-panel guest redirects.
        $middleware->redirectUsersTo(function (Request $request) {
            if (Auth::guard('employee')->check()) {
                return route('employee.dashboard');
            }

            if (Auth::guard('sales')->check()) {
                return route('rep.dashboard');
            }

            if (Auth::guard('support')->check()) {
                return route('support.dashboard');
            }

            $user = Auth::guard('web')->user();
            if ($user && method_exists($user, 'isAdmin') && $user->isAdmin()) {
                return route('admin.dashboard');
            }

            return route('client.dashboard');
        });

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) return route('admin.login');
            if ($request->is('employee') || $request->is('employee/*')) return route('employee.login');
            if ($request->is('sales') || $request->is('sales/*')) return route('sales.login');
            if ($request->is('support') || $request->is('support/*')) return route('support.login');
            return route('login');
        });

        $middleware->validateCsrfTokens(except: [
            'payments/sslcommerz/*',
            'payments/bkash/*',
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'admin.role' => \App\Http\Middleware\EnsureAdminRole::class,
            'client' => \App\Http\Middleware\EnsureClient::class,
            'client.block' => \App\Http\Middleware\PreventBlockedClientAccess::class,
            'client.notice' => \App\Http\Middleware\ShareClientInvoiceStatus::class,
            'project.client' => \App\Http\Middleware\EnsureProjectClientAccess::class,
            'project.financial' => \App\Http\Middleware\BlockProjectSpecificFinancial::class,
            'verify.api.signature' => \App\Http\Middleware\VerifyApiSignature::class,
            'restrict.cron' => \App\Http\Middleware\RestrictCronAccess::class,
            'employee' => \App\Http\Middleware\EnsureEmployee::class,
            'employee.activity' => \App\Http\Middleware\TrackEmployeeActivity::class,
            'salesrep' => \App\Http\Middleware\EnsureSalesRep::class,
            'support' => \App\Http\Middleware\EnsureSupport::class,
            'user.activity' => \App\Http\Middleware\TrackAuthenticatedUserActivity::class,
            'nocache' => \App\Http\Middleware\NoCacheHeaders::class,
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

            $loginRoute = match (true) {
                $request->is('admin') || $request->is('admin/*') => 'admin.login',
                $request->is('employee') || $request->is('employee/*') => 'employee.login',
                $request->is('sales') || $request->is('sales/*') => 'sales.login',
                $request->is('support') || $request->is('support/*') => 'support.login',
                default => 'login',
            };

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

            $loginRoute = match (true) {
                $request->is('admin') || $request->is('admin/*') => 'admin.login',
                $request->is('employee') || $request->is('employee/*') => 'employee.login',
                $request->is('sales') || $request->is('sales/*') => 'sales.login',
                $request->is('support') || $request->is('support/*') => 'support.login',
                default => 'login',
            };

            return redirect()
                ->route($loginRoute)
                ->with('status', 'Session expired. Please log in again.');
        });
    })->create();
