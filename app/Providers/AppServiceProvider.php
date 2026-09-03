<?php

namespace App\Providers;

use App\Enums\MailCategory;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\SalesRepresentative;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Observers\ProjectTaskObserver;
use App\Observers\InvoiceObserver;
use App\Services\AuthFresh\LoginService;
use App\Services\ApptimaticEmailStubRepository;
use App\Support\Branding;
use App\Support\DateTimeFormat;
use App\Support\MailCategoryContext;
use App\Support\SystemLogger;
use App\Support\UrlResolver;
use App\Services\HeaderStatsService;
use App\Services\SettingsService;
use App\Services\TaskQueryService;
use App\Services\Mail\ImapInboxService;
use App\Services\Mail\MailFromResolver;
use App\Services\Mail\MailSessionService;
use App\Services\Mail\MailSender;
use DateTimeZone;
use Illuminate\Cache\RateLimiting\Limit;
use App\Events\InvoiceOverdue;
use App\Events\LicenseBlocked;
use Illuminate\Support\Facades\Event;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use PHPUnit\Framework\Assert;
use Symfony\Component\Mime\Address;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->loadRouteHelpers();
        $this->app->singleton(\App\Services\CommissionService::class);
        // Scoped so the Inertia middleware and the Blade composer share one
        // instance (and therefore one set of badge queries) per request.
        $this->app->scoped(HeaderStatsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerTestCacheMacros();
        $this->registerRateLimiters();
        $this->registerEmailLogListener();
        $this->registerAutomationEventListeners();
        ProjectTask::observe(ProjectTaskObserver::class);
        Invoice::observe(InvoiceObserver::class);

        try {
            $portalUrl = UrlResolver::portalUrl();
            if ($portalUrl !== '') {
                config(['app.url' => $portalUrl]);
                // Avoid forcing HTTP request URL roots from DB setting to prevent
                // accidental path-prefixed route generation (e.g. /admin/login).
                if ($this->app->runningInConsole()) {
                    URL::forceRootUrl($portalUrl);
                    $scheme = parse_url($portalUrl, PHP_URL_SCHEME);
                    if (is_string($scheme) && $scheme !== '') {
                        URL::forceScheme($scheme);
                    }
                }
                config(['filesystems.disks.public.url' => $portalUrl . '/storage']);
            }

            $companyName = Setting::getValue('company_name') ?: config('app.name');
            $logoPath = Setting::getValue('company_logo_path');
            $faviconPath = Setting::getValue('company_favicon_path');
            $timeZone = Setting::getValue('time_zone', config('app.timezone'));
            $dateFormat = DateTimeFormat::datePattern();
            $timeFormat = DateTimeFormat::timePattern();
            $dateTimeFormat = DateTimeFormat::dateTimePattern();

            if (is_string($timeZone) && $timeZone !== '' && in_array($timeZone, DateTimeZone::listIdentifiers(), true)) {
                config(['app.timezone' => $timeZone]);
                date_default_timezone_set($timeZone);
            }

            config(['app.date_format' => $dateFormat]);
            config(['app.time_format' => $timeFormat]);
            config(['app.datetime_format' => $dateTimeFormat]);

            $brand = [
                'company_name' => $companyName ?: 'MyApptimatic',
                'company_email' => Setting::getValue('company_email'),
                'pay_to_text' => Setting::getValue('pay_to_text'),
                'logo_url' => Branding::url($logoPath),
                'favicon_url' => Branding::url($faviconPath),
            ];

            View::share('portalBranding', $brand);
            View::share('globalDateFormat', $dateFormat);
            View::share('globalTimeFormat', $timeFormat);
            View::share('globalDateTimeFormat', $dateTimeFormat);
            View::share('globalTimeZone', $timeZone);

            // Badge counts live in HeaderStatsService so the Blade composer and
            // the Inertia middleware read the same numbers. The service memoises
            // per request, so asking twice costs one set of queries.
            View::composer('app', function ($view) {
                $stats = app(HeaderStatsService::class);

                $view->with('adminHeaderStats', $stats->admin(request()));
                $view->with('employeeHeaderStats', $stats->employee());
                $view->with('clientHeaderStats', $stats->client());
                $view->with('repHeaderStats', $stats->salesRep());
            });

            $settingsService = app(SettingsService::class);
            config($settingsService->recaptchaConfig());

        } catch (\Throwable $e) {
            View::share('portalBranding', [
                'company_name' => config('app.name'),
                'company_email' => null,
                'pay_to_text' => null,
                'logo_url' => null,
                'favicon_url' => null,
            ]);

            View::share('globalDateFormat', 'd-m-Y');
            View::share('globalTimeFormat', 'h:i A');
            View::share('globalDateTimeFormat', 'd-m-Y h:i A');
            View::share('globalTimeZone', config('app.timezone'));

            $emptyStats = HeaderStatsService::empty();
            View::share('adminHeaderStats', $emptyStats['admin']);
            View::share('employeeHeaderStats', $emptyStats['employee']);
            View::share('clientHeaderStats', $emptyStats['client']);
            View::share('repHeaderStats', $emptyStats['rep']);
        }
    }

    private function registerTestCacheMacros(): void
    {
        if (! $this->app->environment('testing') || Cache::hasMacro('fake')) {
            return;
        }

        Cache::macro('fake', function () {
            $repository = new CacheRepository(new ArrayStore());
            Cache::swap($repository);
            return $repository;
        });

        Cache::macro('assertHas', function (string $key) {
            Assert::assertTrue(Cache::has($key), "Failed asserting that cache has key [{$key}].");
        });
    }

    private function registerRateLimiters(): void
    {
        LoginService::registerRateLimiter();

        RateLimiter::for('license-verify', function ($request) {
            $ip = $request->ip() ?? 'unknown';
            $key = (string) $request->input('license_key', 'none');

            return [
                Limit::perMinute(30)->by($ip),
                Limit::perMinute(60)->by($ip.'|'.$key),
            ];
        });

        RateLimiter::for('cron-endpoint', function ($request) {
            return Limit::perMinute(10)->by($request->ip() ?? 'unknown');
        });

        RateLimiter::for('payment-callbacks', function ($request) {
            return Limit::perMinute(10)->by($request->ip() ?? 'unknown');
        });

        RateLimiter::for('mail-login', function ($request) {
            $identity = (string) ($request->user()?->id ?? 'guest');
            $email = strtolower((string) $request->input('email', 'none'));
            $ip = (string) ($request->ip() ?? 'unknown');
            $maxAttempts = max((int) config('apptimatic_email.login_rate_limit_attempts', 5), 1);
            $decayMinutes = max((int) config('apptimatic_email.login_rate_limit_decay_minutes', 10), 1);

            return Limit::perMinutes($decayMinutes, $maxAttempts)
                ->by($identity.'|'.$email.'|'.$ip)
                ->response(function (Request $request, array $headers) use ($decayMinutes) {
                    $retryAfter = (int) ($headers['Retry-After'] ?? ($decayMinutes * 60));
                    $retryAfter = max($retryAfter, 1);

                    return back()
                        ->withErrors([
                            'email' => "Too many email login attempts. Please try again in {$retryAfter} seconds.",
                        ])
                        ->withInput($request->only('email'));
                });
        });
    }

    private function registerEmailLogListener(): void
    {
        Event::listen(MessageSending::class, function (MessageSending $event) {
            $mailer = property_exists($event, 'mailer') ? $event->mailer : null;
            $category = $this->resolveCategoryForMessage($event->message);
            $this->applyFromRoutingForCategory($event->message, $category);
            // $this->logEmailEvent('Email sending.', $event->message, $mailer, $category, 'info');
        });

        Event::listen(MessageSent::class, function (MessageSent $event) {
            $mailer = property_exists($event, 'mailer') ? $event->mailer : null;
            $category = $this->resolveCategoryForMessage($event->message);
            $this->logEmailEvent('Email sent.', $event->message, $mailer, $category, 'info');
        });

        $failedEventClass = 'Illuminate\\Mail\\Events\\MessageFailed';
        if (class_exists($failedEventClass)) {
            Event::listen($failedEventClass, function (object $event): void {
                if (! property_exists($event, 'message')) {
                    return;
                }

                $mailer = property_exists($event, 'mailer') ? (is_string($event->mailer) ? $event->mailer : null) : null;
                $message = $event->message;
                if (! is_object($message)) {
                    return;
                }

                $category = $this->resolveCategoryForMessage($message);
                $failure = property_exists($event, 'exception') && $event->exception instanceof \Throwable
                    ? $event->exception->getMessage()
                    : null;

                $this->logEmailEvent('Email failed to send.', $message, $mailer, $category, 'error', [
                    'failure' => $failure,
                ]);
            });
        }
    }

    private function logEmailEvent(
        string $messageLabel,
        object $message,
        ?string $mailer,
        string $category,
        string $level = 'info',
        array $extra = []
    ): void
    {
        $to = $this->extractAddresses($message, 'getTo');
        $from = $this->extractAddresses($message, 'getFrom');

        $subject = method_exists($message, 'getSubject') ? (string) $message->getSubject() : '';
        $messageId = null;

        if (method_exists($message, 'getHeaders')) {
            $headers = $message->getHeaders();
            if ($headers->has('Message-ID')) {
                $messageId = (string) $headers->get('Message-ID')->getBodyAsString();
            }
        }

        $context = array_merge([
            'subject' => $subject,
            'to' => $to,
            'to_count' => count($to),
            'from' => $from,
            'from_address' => $from[0] ?? null,
            'category' => MailCategory::normalize($category),
            'mailer' => $mailer,
            'message_id' => $messageId,
        ], $extra);

        if ((bool) config('system_mail.log_bodies', false)) {
            $context['html'] = method_exists($message, 'getHtmlBody') ? (string) $message->getHtmlBody() : '';
            $context['text'] = method_exists($message, 'getTextBody') ? (string) $message->getTextBody() : '';
        }

        SystemLogger::write('email', $messageLabel, $context, level: $level);
    }

    private function resolveCategoryForMessage(object $message): string
    {
        if (method_exists($message, 'getHeaders')) {
            $headers = $message->getHeaders();
            $headerName = 'X-Apptimatic-Mail-Category';
            if ($headers->has($headerName)) {
                return MailCategory::normalize((string) $headers->get($headerName)->getBodyAsString());
            }
        }

        $contextCategory = MailCategoryContext::current();
        if ($contextCategory !== null) {
            return MailCategory::normalize($contextCategory);
        }

        $resolver = app(MailFromResolver::class);
        $from = $this->extractAddresses($message, 'getFrom');
        if (! empty($from)) {
            return $resolver->categoryForAddress($from[0]);
        }

        return MailCategory::SYSTEM;
    }

    private function applyFromRoutingForCategory(object $message, string $category): void
    {
        $resolver = app(MailFromResolver::class);
        $resolvedFrom = $resolver->resolve($category);
        $resolvedAddress = strtolower(trim((string) ($resolvedFrom['address'] ?? '')));
        if ($resolvedAddress === '' || ! method_exists($message, 'from')) {
            return;
        }

        $existingFrom = $this->extractAddresses($message, 'getFrom');
        if (in_array($resolvedAddress, $existingFrom, true)) {
            return;
        }

        $legacyDefault = strtolower(trim((string) config('mail.from.address', '')));
        $isLegacyFrom = ! empty($existingFrom)
            && $legacyDefault !== ''
            && in_array($legacyDefault, $existingFrom, true);

        if (empty($existingFrom) || $isLegacyFrom) {
            $message->from(new Address(
                $resolvedFrom['address'],
                (string) ($resolvedFrom['name'] ?? '')
            ));
        }

        if (method_exists($message, 'getHeaders')) {
            $headers = $message->getHeaders();
            $headerName = 'X-Apptimatic-Mail-Category';
            if ($headers->has($headerName)) {
                $headers->remove($headerName);
            }
            $headers->addTextHeader($headerName, MailCategory::normalize($category));
        }
    }

    /**
     * @return array<int, string>
     */
    private function extractAddresses(object $message, string $getter): array
    {
        if (! method_exists($message, $getter)) {
            return [];
        }

        $addresses = $message->{$getter}();
        if (! is_array($addresses)) {
            return [];
        }

        $results = [];
        foreach ($addresses as $address) {
            $email = strtolower(trim((string) $address->getAddress()));
            if ($email !== '') {
                $results[] = $email;
            }
        }

        return array_values(array_unique($results));
    }

    private function registerAutomationEventListeners(): void
    {
        Event::listen(InvoiceOverdue::class, function (InvoiceOverdue $event) {
            $invoice = $event->invoice;

            SystemLogger::write('module', 'Invoice overdue event received.', [
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'status' => $invoice->status,
            ]);

            $to = Setting::getValue('company_email') ?: config('mail.from.address');
            if ($to) {
                try {
                    app(MailSender::class)->sendRaw(
                        MailCategory::BILLING,
                        $to,
                        "Invoice #{$invoice->id} is overdue. Status: {$invoice->status}",
                        'Invoice overdue alert'
                    );
                } catch (\Throwable) {
                    // Do not break on mail failure.
                }
            }
        });

        Event::listen(LicenseBlocked::class, function (LicenseBlocked $event) {
            $license = $event->license;

            SystemLogger::write('module', 'License blocked during verification.', [
                'license_id' => $license->id,
                'subscription_id' => $license->subscription_id,
                'customer_id' => $license->subscription?->customer_id,
                'reason' => $event->reason,
                'context' => $event->context,
            ]);

            $to = Setting::getValue('company_email') ?: config('mail.from.address');
            if ($to) {
                try {
                    $requestId = $event->context['request_id'] ?? '';
                    $reason = $event->reason;
                    app(MailSender::class)->sendRaw(
                        MailCategory::BILLING,
                        $to,
                        "License {$license->id} blocked during verification. Reason: {$reason}. Request ID: {$requestId}",
                        'License blocked alert'
                    );
                } catch (\Throwable) {
                    // swallow mail errors
                }
            }
        });
    }


    private function loadRouteHelpers(): void
    {
        $primaryPath = app_path('Helpers/RouteHelper.php');
        if (is_file($primaryPath)) {
            require_once $primaryPath;
            return;
        }

        $fallbackPath = app_path('Support/RouteHelperFunctions.php');
        if (is_file($fallbackPath)) {
            require_once $fallbackPath;
        }
    }
}
