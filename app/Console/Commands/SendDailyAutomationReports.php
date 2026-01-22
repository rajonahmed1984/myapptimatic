<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Mail\CronActivityReportMail;
use App\Mail\LicenseSyncReportMail;
use App\Models\CronRun;
use App\Models\LicenseSyncRun;
use App\Models\Setting;
use App\Models\User;
use App\Support\Branding;
use App\Support\UrlResolver;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendDailyAutomationReports extends Command
{
    protected $signature = 'reports:daily {--force : Send the reports regardless of schedule and last sent date}';

    protected $description = 'Send daily cron activity and license synchronisation reports.';

    public function handle(): int
    {
        $timeZone = Setting::getValue('time_zone', config('app.timezone', 'UTC')) ?: 'UTC';
        $now = Carbon::now($timeZone);
        $force = (bool) $this->option('force');

        if (! $force && ! $this->isScheduledMinute($now)) {
            return self::SUCCESS;
        }

        $recipients = $this->adminRecipients();
        if (empty($recipients)) {
            $this->warn('No master admin email configured; skipping daily reports.');
            return self::SUCCESS;
        }

        $shouldSendCron = $force || ! $this->sentToday('daily_cron_activity_report_sent_at', $now);
        $shouldSendLicense = $force || ! $this->sentToday('daily_license_sync_report_sent_at', $now);

        if (! $shouldSendCron && ! $shouldSendLicense) {
            return self::SUCCESS;
        }

        $companyName = Setting::getValue('company_name', config('app.name'));
        $logoUrl = Branding::url(Setting::getValue('company_logo_path'));
        $portalUrl = UrlResolver::portalUrl();
        $portalLoginUrl = $portalUrl . '/admin';
        $dateFormat = Setting::getValue('date_format', config('app.date_format', 'd-m-Y'));

        if ($shouldSendCron) {
            $cronPayload = $this->buildCronActivityPayload($now, $timeZone, $companyName, $logoUrl, $portalUrl, $portalLoginUrl, $dateFormat);
            $this->sendMailable($recipients, new CronActivityReportMail($cronPayload));
            Setting::setValue('daily_cron_activity_report_sent_at', $now->toDateTimeString());
        }

        if ($shouldSendLicense) {
            $licensePayload = $this->buildLicenseSyncPayload($now, $timeZone, $companyName, $logoUrl, $portalUrl, $portalLoginUrl, $dateFormat);
            $this->sendMailable($recipients, new LicenseSyncReportMail($licensePayload));
            Setting::setValue('daily_license_sync_report_sent_at', $now->toDateTimeString());
        }

        return self::SUCCESS;
    }

    private function isScheduledMinute(Carbon $now): bool
    {
        $timeOfDay = (string) Setting::getValue('automation_time_of_day', '00:00');
        return $now->format('H:i') === $timeOfDay;
    }

    private function sentToday(string $key, Carbon $now): bool
    {
        $lastSent = Setting::getValue($key);
        if (! $lastSent) {
            return false;
        }

        try {
            return Carbon::parse($lastSent, $now->getTimezone())->isSameDay($now);
        } catch (\Throwable) {
            return false;
        }
    }

    private function adminRecipients(): array
    {
        $emails = User::query()
            ->where('role', Role::MASTER_ADMIN)
            ->whereNotNull('email')
            ->pluck('email')
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $extra = Setting::getValue('system_notification_emails');
        if (is_string($extra) && trim($extra) !== '') {
            $extras = collect(explode(',', $extra))
                ->map(fn ($email) => strtolower(trim($email)))
                ->filter()
                ->all();
            $emails = array_values(array_unique(array_merge($emails, $extras)));
        }

        if (empty($emails)) {
            $fallback = Setting::getValue('company_email') ?: config('mail.from.address');
            if ($fallback) {
                $emails = [strtolower(trim((string) $fallback))];
            }
        }

        return $emails;
    }

    private function buildCronActivityPayload(
        Carbon $now,
        string $timeZone,
        string $companyName,
        ?string $logoUrl,
        string $portalUrl,
        string $portalLoginUrl,
        string $dateFormat
    ): array {
        $start = $now->copy()->subDay();

        $recentRuns = CronRun::query()
            ->whereBetween('started_at', [$start, $now])
            ->orderByDesc('started_at')
            ->get();

        $latestByCommand = $recentRuns
            ->groupBy('command')
            ->map(fn ($items) => $items->first())
            ->values();

        $rows = $latestByCommand->map(function (CronRun $run) {
            $duration = $run->duration_ms
                ? round($run->duration_ms / 1000, 2) . 's'
                : '--';

            return [
                'command' => $run->command,
                'last_run_at' => $run->started_at,
                'status' => $run->status,
                'duration' => $duration,
                'output' => Str::limit($run->error_excerpt ?: $run->output_excerpt ?: '', 120) ?: '--',
            ];
        })->all();

        $failedRuns = CronRun::query()
            ->whereBetween('started_at', [$start, $now])
            ->where('status', 'failed')
            ->count();

        $lastRuns = CronRun::query()
            ->select('command', DB::raw('MAX(started_at) as last_started_at'))
            ->groupBy('command')
            ->get();

        $missedRuns = $lastRuns->filter(function ($row) use ($start, $timeZone) {
            if (! $row->last_started_at) {
                return false;
            }
            return Carbon::parse($row->last_started_at, $timeZone)->lt($start);
        })->count();

        return [
            'subject' => 'Apptimatic Cron Job Activity',
            'companyName' => $companyName,
            'logoUrl' => $logoUrl,
            'portalUrl' => $portalUrl,
            'portalLoginUrl' => $portalLoginUrl,
            'portalLoginLabel' => 'log in to the admin area',
            'dateFormat' => $dateFormat,
            'timeZone' => $timeZone,
            'rangeStart' => $start,
            'rangeEnd' => $now,
            'totalRuns' => $recentRuns->count(),
            'failedRuns' => $failedRuns,
            'missedRuns' => $missedRuns,
            'rows' => $rows,
        ];
    }

    private function buildLicenseSyncPayload(
        Carbon $now,
        string $timeZone,
        string $companyName,
        ?string $logoUrl,
        string $portalUrl,
        string $portalLoginUrl,
        string $dateFormat
    ): array {
        $start = $now->copy()->subDay();

        $syncRun = LicenseSyncRun::query()
            ->where('run_at', '>=', $start)
            ->orderByDesc('run_at')
            ->first();

        $hasRun = (bool) $syncRun;
        $details = $syncRun?->errors_json ?? [];

        return [
            'subject' => 'Apptimatic Licenses Synchronisation Cron Report',
            'companyName' => $companyName,
            'logoUrl' => $logoUrl,
            'portalUrl' => $portalUrl,
            'portalLoginUrl' => $portalLoginUrl,
            'portalLoginLabel' => 'log in to the admin area',
            'dateFormat' => $dateFormat,
            'timeZone' => $timeZone,
            'rangeStart' => $start,
            'rangeEnd' => $now,
            'hasRun' => $hasRun,
            'runAt' => $syncRun?->run_at,
            'counts' => [
                'total_checked' => $syncRun?->total_checked ?? 0,
                'updated_count' => $syncRun?->updated_count ?? 0,
                'expired_count' => $syncRun?->expired_count ?? 0,
                'suspended_count' => $syncRun?->suspended_count ?? 0,
                'invalid_count' => $syncRun?->invalid_count ?? 0,
                'domain_updates_count' => $syncRun?->domain_updates_count ?? 0,
                'domain_mismatch_count' => $syncRun?->domain_mismatch_count ?? 0,
                'api_failures_count' => $syncRun?->api_failures_count ?? 0,
                'failed_count' => $syncRun?->failed_count ?? 0,
            ],
            'details' => is_array($details) ? $details : [],
        ];
    }

    private function sendMailable(array $recipients, $mailable): void
    {
        if (config('queue.default') === 'sync') {
            Mail::to($recipients)->sendNow($mailable);
            return;
        }

        Mail::to($recipients)->queue($mailable);
    }
}
