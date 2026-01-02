<?php

namespace App\Providers;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Support\Branding;
use App\Support\SystemLogger;
use App\Support\UrlResolver;
use DateTimeZone;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerEmailLogListener();

        try {
            $portalUrl = UrlResolver::portalUrl();
            if ($portalUrl !== '') {
                config(['app.url' => $portalUrl]);
                URL::forceRootUrl($portalUrl);
                $scheme = parse_url($portalUrl, PHP_URL_SCHEME);
                if (is_string($scheme) && $scheme !== '') {
                    URL::forceScheme($scheme);
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
                $view->with('adminHeaderStats', [
                    'pending_orders' => Order::where('status', 'pending')->count(),
                    'overdue_invoices' => Invoice::where('status', 'overdue')->count(),
                    'tickets_waiting' => SupportTicket::where('status', 'customer_reply')->count(),
                    'pending_manual_payments' => PaymentProof::where('status', 'pending')->count(),
                ]);
            });

            View::composer('layouts.client', function ($view) {
                $customer = auth()->user()?->customer;
                $view->with('clientHeaderStats', [
                    'pending_admin_replies' => $customer
                        ? SupportTicket::where('customer_id', $customer->id)
                            ->where('status', 'answered')
                            ->count()
                        : 0,
                ]);
            });

            if (! empty($brand['company_email'])) {
                config(['mail.from.address' => $brand['company_email']]);
            }

            if (! empty($brand['company_name'])) {
                config(['mail.from.name' => $brand['company_name']]);
            }

            $recaptchaConfig = [
                'recaptcha.enabled' => (bool) Setting::getValue('recaptcha_enabled', config('recaptcha.enabled')),
                'recaptcha.site_key' => Setting::getValue('recaptcha_site_key', config('recaptcha.site_key')),
                'recaptcha.secret_key' => Setting::getValue('recaptcha_secret_key', config('recaptcha.secret_key')),
                'recaptcha.project_id' => Setting::getValue('recaptcha_project_id', config('recaptcha.project_id')),
                'recaptcha.api_key' => Setting::getValue('recaptcha_api_key', config('recaptcha.api_key')),
                'recaptcha.score_threshold' => (float) Setting::getValue('recaptcha_score_threshold', config('recaptcha.score_threshold')),
            ];

            config($recaptchaConfig);

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
                'pending_manual_payments' => 0,
            ]);
        }
    }

    private function registerEmailLogListener(): void
    {
        Event::listen(MessageSent::class, function (MessageSent $event) {
            $message = $event->message;
            $to = [];
            $from = [];

            if (method_exists($message, 'getTo') && is_array($message->getTo())) {
                foreach ($message->getTo() as $address) {
                    $to[] = strtolower($address->getAddress());
                }
            }

            if (method_exists($message, 'getFrom') && is_array($message->getFrom())) {
                foreach ($message->getFrom() as $address) {
                    $from[] = strtolower($address->getAddress());
                }
            }

            $subject = method_exists($message, 'getSubject') ? (string) $message->getSubject() : '';
            $html = method_exists($message, 'getHtmlBody') ? (string) $message->getHtmlBody() : '';
            $text = method_exists($message, 'getTextBody') ? (string) $message->getTextBody() : '';

            SystemLogger::write('email', 'Email sent.', [
                'subject' => $subject,
                'to' => $to,
                'from' => $from,
                'html' => $html,
                'text' => $text,
                'mailer' => $event->mailer ?? null,
            ]);
        });
    }
}
