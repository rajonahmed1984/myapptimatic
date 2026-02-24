<?php

use App\Http\Middleware\TrustProxies;
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Support\AjaxResponse;
use App\Support\AuthFresh\AdminAccess;
use App\Support\AuthFresh\Portal;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(
            at: TrustProxies::proxies(),
            headers: TrustProxies::headers()
        );

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
            if (AdminAccess::canAccess($user)) {
                return route('admin.dashboard');
            }

            return route('client.dashboard');
        });

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return '/admin/login';
            }

            if ($request->is('employee') || $request->is('employee/*')) {
                return '/employee/login';
            }

            if ($request->is('sales') || $request->is('sales/*')) {
                return '/sales/login';
            }

            if ($request->is('support') || $request->is('support/*')) {
                return '/support/login';
            }

            return '/login';
        });

        $middleware->validateCsrfTokens(except: [
            'payments/sslcommerz/*',
            'payments/bkash/*',
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdminPanelAccess::class,
            'admin.panel' => \App\Http\Middleware\EnsureAdminPanelAccess::class,
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
            'login.trace' => \App\Http\Middleware\LoginTrace::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandlePartialResponse::class,
            \App\Http\Middleware\NormalizeAjaxRedirectResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $logCsrfMismatch = function (Request $request, string $type): void {
            $sessionId = null;
            if ($request->hasSession()) {
                $sessionId = $request->session()->getId();
            }

            $sessionCookieName = (string) config('session.cookie');

            Log::warning('[CSRF_419]', [
                'type' => $type,
                'path' => $request->path(),
                'method' => $request->method(),
                'host' => $request->getHost(),
                'scheme' => $request->getScheme(),
                'is_secure' => $request->isSecure(),
                'session_cookie_name' => $sessionCookieName,
                'request_has_session_cookie' => $sessionCookieName !== '' && $request->cookies->has($sessionCookieName),
                'session_domain' => config('session.domain'),
                'session_id' => $sessionId,
                'csrf_token_present' => $request->has('_token'),
                'x_forwarded_proto' => $request->headers->get('x-forwarded-proto'),
                'x_forwarded_for' => $request->headers->get('x-forwarded-for'),
                'x_forwarded_host' => $request->headers->get('x-forwarded-host'),
            ]);
        };

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (config('app.debug')) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Not found.'], 404);
            }

            return response()->view('errors.404', [], 404);
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return null;
            }

            if (! AjaxResponse::ajaxFromRequest($request)) {
                return null;
            }

            return AjaxResponse::ajaxValidation(
                $exception->errors(),
                null,
                'Validation failed.'
            );
        });

        $exceptions->render(function (TokenMismatchException $exception, Request $request) use ($logCsrfMismatch) {
            $logCsrfMismatch($request, TokenMismatchException::class);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Session expired. Please log in again.'], 419);
            }

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            auth()->logout();

            $portal = Portal::fromRequest($request);

            return redirect()
                ->to(Portal::portalLoginUrl($portal))
                ->with('status', 'Session expired. Please log in again.');
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) use ($logCsrfMismatch) {
            if ($exception->getStatusCode() !== 419) {
                return null;
            }

            $logCsrfMismatch($request, $exception::class);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Session expired. Please log in again.'], 419);
            }

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            auth()->logout();

            $portal = Portal::fromRequest($request);

            return redirect()
                ->to(Portal::portalLoginUrl($portal))
                ->with('status', 'Session expired. Please log in again.');
        });
    })->create();
