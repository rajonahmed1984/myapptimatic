<?php

namespace App\Providers;

use App\Enums\MailCategory;
use App\Models\Invoice;
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
use App\Services\AuthFresh\LoginService;
use App\Services\ApptimaticEmailStubRepository;
use App\Support\Branding;
use App\Support\MailCategoryContext;
use App\Support\SystemLogger;
use App\Support\UrlResolver;
use App\Services\SettingsService;
use App\Services\TaskQueryService;
use App\Services\Mail\MailFromResolver;
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
            $dateFormat = Setting::getValue('date_format', 'd-m-Y');

            if (is_string($timeZone) && $timeZone !== '' && in_array($timeZone, DateTimeZone::listIdentifiers(), true)) {
                config(['app.timezone' => $timeZone]);
                date_default_timezone_set($timeZone);
            }

            if (! is_string($dateFormat) || $dateFormat === '') {
                $dateFormat = 'd-m-Y';
            }
            config(['app.date_format' => $dateFormat]);

            $brand = [
                'company_name' => $companyName ?: 'MyApptimatic',
                'company_email' => Setting::getValue('company_email'),
                'pay_to_text' => Setting::getValue('pay_to_text'),
                'logo_url' => Branding::url($logoPath),
                'favicon_url' => Branding::url($faviconPath),
            ];

            View::share('portalBranding', $brand);
            View::share('globalDateFormat', $dateFormat);
            View::share('globalTimeZone', $timeZone);

            View::composer('layouts.admin', function ($view) {
                $user = auth()->user();
                $employeeHeaderStats = [
                    'task_badge' => 0,
                    'unread_chat' => 0,
                ];
                $adminTaskBadge = 0;
                $adminUnreadChat = 0;

                if ($user && $user->isEmployee()) {
                    $employee = request()->attributes->get('employee');
                    if (! ($employee instanceof Employee)) {
                        $employee = $user->employee;
                    }

                    if ($employee) {
                        $taskQueryService = app(TaskQueryService::class);
                        if ($taskQueryService->canViewTasks($user)) {
                            $taskSummary = $taskQueryService->tasksSummaryForUser($user);
                            $employeeHeaderStats['task_badge'] = (int) (($taskSummary['open'] ?? 0) + ($taskSummary['in_progress'] ?? 0));
                        }

                        // Keep sidebar unread count aligned with employee chat listing
                        // by using the same relation-scoped project set.
                        $projectIds = $employee->projects()
                            ->pluck('projects.id');

                        if ($projectIds->isNotEmpty()) {
                            $employeeUnreadByProject = DB::table('project_messages as pm')
                                ->select('pm.project_id', DB::raw('COUNT(*) as unread'))
                                ->whereIn('pm.project_id', $projectIds->all())
                                ->whereRaw(
                                    'pm.id > COALESCE((SELECT MAX(pmr.last_read_message_id) FROM project_message_reads as pmr WHERE pmr.project_id = pm.project_id AND pmr.reader_type = ? AND pmr.reader_id = ?), 0)',
                                    ['employee', $employee->id]
                                )
                                ->groupBy('pm.project_id')
                                ->pluck('unread', 'pm.project_id')
                                ->map(fn ($count) => (int) $count);

                            $employeeHeaderStats['unread_chat'] = (int) $employeeUnreadByProject->sum();
                        }
                    }
                }

                if ($user && $user->isAdmin()) {
                    $taskQueryService = app(TaskQueryService::class);
                    if ($taskQueryService->canViewTasks($user)) {
                        $taskSummary = $taskQueryService->tasksSummaryForUser($user);
                        $adminTaskBadge = (int) (($taskSummary['open'] ?? 0) + ($taskSummary['in_progress'] ?? 0));
                    }

                    $adminUnreadChat = (int) DB::table('project_messages as pm')
                        ->whereRaw(
                            'pm.id > COALESCE((SELECT MAX(pmr.last_read_message_id) FROM project_message_reads as pmr WHERE pmr.project_id = pm.project_id AND pmr.reader_type = ? AND pmr.reader_id = ?), 0)',
                            ['user', $user->id]
                        )
                        ->count();
                }

                $view->with('adminHeaderStats', [
                    'pending_orders' => Order::where('status', 'pending')->count(),
                    'overdue_invoices' => Invoice::where('status', 'overdue')->count(),
                    'tickets_waiting' => SupportTicket::where('status', 'customer_reply')->count(),
                    'open_support_tickets' => SupportTicket::where('status', 'open')->count(),
                    'pending_manual_payments' => PaymentProof::where('status', 'pending')->count(),
                    'pending_leave_requests' => LeaveRequest::where('status', 'pending')->count(),
                    'tasks_badge' => $adminTaskBadge,
                    'unread_chat' => $adminUnreadChat,
                    'apptimatic_email_unread' => app(ApptimaticEmailStubRepository::class)->unreadCount(),
                ]);
                $view->with('employeeHeaderStats', $employeeHeaderStats);
            });

            View::composer('layouts.client', function ($view) {
                $user = auth()->user();
                $customer = $user?->customer;
                $unreadChatCount = 0;
                $taskBadgeCount = 0;

                if ($user) {
                    $projectIds = collect();

                    if ($user->isClientProject()) {
                        if ($user->project_id) {
                            $projectIds = collect([$user->project_id]);
                        }
                    } elseif ($user->isClient()) {
                        $projectIds = Project::where('customer_id', $user->customer_id)->pluck('id');
                    }

                    if ($projectIds->isNotEmpty()) {
                        $unreadChatCount = (int) DB::table('project_messages as pm')
                            ->leftJoin('project_message_reads as pmr', function ($join) use ($user) {
                                $join->on('pmr.project_id', '=', 'pm.project_id')
                                    ->where('pmr.reader_type', 'user')
                                    ->where('pmr.reader_id', $user->id);
                            })
                            ->whereIn('pm.project_id', $projectIds->all())
                            ->whereRaw('pm.id > COALESCE(pmr.last_read_message_id, 0)')
                            ->count();
                    }

                    $taskQueryService = app(TaskQueryService::class);
                    if ($taskQueryService->canViewTasks($user)) {
                        $taskSummary = $taskQueryService->tasksSummaryForUser($user);
                        $taskBadgeCount = (int) (($taskSummary['open'] ?? 0) + ($taskSummary['in_progress'] ?? 0));
                    }
                }

                $view->with('clientHeaderStats', [
                    'pending_admin_replies' => $customer
                        ? SupportTicket::where('customer_id', $customer->id)
                            ->where('status', 'answered')
                            ->count()
                        : 0,
                    'unread_chat' => $unreadChatCount,
                    'task_badge' => $taskBadgeCount,
                ]);
            });

            View::composer('layouts.rep', function ($view) {
                $user = auth()->user();
                $repHeaderStats = [
                    'task_badge' => 0,
                    'unread_chat' => 0,
                ];

                if ($user && $user->isSales()) {
                    $salesRep = request()->attributes->get('salesRep');
                    if (! ($salesRep instanceof SalesRepresentative)) {
                        $salesRep = SalesRepresentative::where('user_id', $user->id)->first();
                    }

                    $taskQueryService = app(TaskQueryService::class);
                    if ($taskQueryService->canViewTasks($user)) {
                        $taskSummary = $taskQueryService->tasksSummaryForUser($user);
                        $repHeaderStats['task_badge'] = (int) (($taskSummary['open'] ?? 0) + ($taskSummary['in_progress'] ?? 0));
                    }

                    if ($salesRep) {
                        $projectIds = $salesRep->projects()->pluck('projects.id');
                        if ($projectIds->isNotEmpty()) {
                            $repHeaderStats['unread_chat'] = (int) DB::table('project_messages as pm')
                                ->leftJoin('project_message_reads as pmr', function ($join) use ($salesRep) {
                                    $join->on('pmr.project_id', '=', 'pm.project_id')
                                        ->where('pmr.reader_type', 'sales_rep')
                                        ->where('pmr.reader_id', $salesRep->id);
                                })
                                ->whereIn('pm.project_id', $projectIds->all())
                                ->whereRaw('pm.id > COALESCE(pmr.last_read_message_id, 0)')
                                ->count();
                        }
                    }
                }

                $view->with('repHeaderStats', $repHeaderStats);
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
            View::share('globalTimeZone', config('app.timezone'));

            View::share('adminHeaderStats', [
                'pending_orders' => 0,
                'overdue_invoices' => 0,
                'tickets_waiting' => 0,
                'open_support_tickets' => 0,
                'pending_manual_payments' => 0,
                'pending_leave_requests' => 0,
            ]);
            View::share('employeeHeaderStats', [
                'task_badge' => 0,
                'unread_chat' => 0,
            ]);
            View::share('repHeaderStats', [
                'task_badge' => 0,
                'unread_chat' => 0,
            ]);
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
    }

    private function registerEmailLogListener(): void
    {
        Event::listen(MessageSending::class, function (MessageSending $event) {
            $mailer = property_exists($event, 'mailer') ? $event->mailer : null;
            $category = $this->resolveCategoryForMessage($event->message);
            $this->applyFromRoutingForCategory($event->message, $category);
            $this->logEmailEvent('Email sending.', $event->message, $mailer, $category, 'info');
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
