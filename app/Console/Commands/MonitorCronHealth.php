<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Support\UrlResolver;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class MonitorCronHealth extends Command
{
    protected $signature = 'cron:monitor';

    protected $description = 'Send an alert if the daily billing cron has not run recently or is failing.';

    public function handle(): int
    {
        $timeZone = Setting::getValue('time_zone', config('app.timezone', 'UTC')) ?: 'UTC';
        $now = Carbon::now($timeZone);

        $lastRunAtString = Setting::getValue('billing_last_run_at');
        $lastRunAt = $lastRunAtString ? Carbon::parse($lastRunAtString, $timeZone) : null;
        $lastStatus = Setting::getValue('billing_last_status');
        $lastAlertAtString = Setting::getValue('billing_health_last_alert_at');
        $lastAlertAt = $lastAlertAtString ? Carbon::parse($lastAlertAtString, $timeZone) : null;

        $thresholdHours = (int) Setting::getValue('cron_health_threshold_hours', 26);
        $cooldownHours = (int) Setting::getValue('cron_health_alert_cooldown_hours', 6);

        $shouldAlert = false;
        $reason = null;

        if (! $lastRunAt) {
            $shouldAlert = true;
            $reason = 'Cron has never completed.';
        } elseif ($lastRunAt->diffInHours($now) > $thresholdHours) {
            $shouldAlert = true;
            $reason = 'Cron has not completed within the expected window.';
        } elseif ($lastStatus === 'failed') {
            $shouldAlert = true;
            $reason = 'Last cron run failed.';
        }

        $recentlyAlerted = $lastAlertAt && $lastAlertAt->diffInHours($now) < $cooldownHours;

        if (! $shouldAlert || $recentlyAlerted) {
            return self::SUCCESS;
        }

        $portalUrl = UrlResolver::portalUrl();
        $cronToken = (string) Setting::getValue('cron_token');
        $cronUrl = $cronToken !== '' ? "{$portalUrl}/cron/billing?token={$cronToken}" : null;

        $to = Setting::getValue('company_email') ?: config('mail.from.address');
        if (! $to) {
            $this->warn('No company email configured; skipping cron health alert.');
            return self::SUCCESS;
        }

        $subject = 'Billing cron attention needed';
        $lastRunText = $lastRunAt ? $lastRunAt->format('Y-m-d H:i:s') . " ({$timeZone})" : 'never';

        try {
            Mail::raw(
                "The daily billing cron has not completed successfully within the expected window.\n\n"
                . "Reason: {$reason}\n"
                . "Last run at: {$lastRunText}\n"
                . "Last status: " . ($lastStatus ?: 'unknown') . "\n"
                . ($cronUrl ? "Manual trigger URL: {$cronUrl}\n" : '')
                . "Portal: {$portalUrl}\n\n"
                . "This alert will repeat every {$cooldownHours} hours until the cron completes successfully.",
                function ($message) use ($to, $subject) {
                    $message->to($to)->subject($subject);
                }
            );
        } catch (\Throwable $e) {
            $this->error('Failed to send cron health alert: ' . $e->getMessage());
            return self::FAILURE;
        }

        Setting::setValue('billing_health_last_alert_at', $now->toDateTimeString());

        $this->info('Cron health alert sent.');

        return self::SUCCESS;
    }
}
