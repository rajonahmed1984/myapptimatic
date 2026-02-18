<?php

namespace App\Console\Commands;

use App\Models\CronRun;
use App\Models\License;
use App\Models\LicenseSyncRun;
use Carbon\Carbon;
use Illuminate\Console\Command;

class LicenseHealthSummary extends Command
{
    protected $signature = 'licenses:health-summary {--hours=24 : Lookback window in hours} {--json : Output JSON only}';

    protected $description = 'Show quick license verification health summary (cron execution + sync freshness).';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $windowStart = now()->subHours($hours);

        $verifyStats = CronRun::query()
            ->where('command', 'verify-licenses')
            ->where('started_at', '>=', $windowStart)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as success, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed, MAX(started_at) as last_started_at, MAX(finished_at) as last_finished_at', ['success', 'failed'])
            ->first();

        $syncStats = LicenseSyncRun::query()
            ->where('run_at', '>=', $windowStart)
            ->selectRaw('COUNT(*) as total_runs, MAX(run_at) as last_run_at, SUM(total_checked) as total_checked, SUM(api_failures_count) as api_failures, SUM(domain_mismatch_count) as domain_mismatches')
            ->first();

        $activeBase = License::query()->where('status', 'active');

        $freshness = [
            'total_active' => (clone $activeBase)->count(),
            'never_verified' => (clone $activeBase)->whereNull('last_verified_at')->count(),
            'verified_within_1h' => (clone $activeBase)->whereNotNull('last_verified_at')->where('last_verified_at', '>=', now()->subHour())->count(),
            'stale_over_1h' => (clone $activeBase)->whereNotNull('last_verified_at')->where('last_verified_at', '<', now()->subHour())->count(),
            'stale_over_24h' => (clone $activeBase)->whereNotNull('last_verified_at')->where('last_verified_at', '<', now()->subDay())->count(),
            'last_verified_latest' => (clone $activeBase)->whereNotNull('last_verified_at')->max('last_verified_at'),
            'last_verified_oldest_active' => (clone $activeBase)->whereNotNull('last_verified_at')->min('last_verified_at'),
        ];

        $payload = [
            'window_hours' => $hours,
            'window_start' => $windowStart->toDateTimeString(),
            'generated_at' => now()->toDateTimeString(),
            'verify_licenses' => [
                'total' => (int) ($verifyStats->total ?? 0),
                'success' => (int) ($verifyStats->success ?? 0),
                'failed' => (int) ($verifyStats->failed ?? 0),
                'last_started_at' => $this->toDateTimeString($verifyStats->last_started_at ?? null),
                'last_finished_at' => $this->toDateTimeString($verifyStats->last_finished_at ?? null),
            ],
            'license_sync_runs' => [
                'total_runs' => (int) ($syncStats->total_runs ?? 0),
                'last_run_at' => $this->toDateTimeString($syncStats->last_run_at ?? null),
                'total_checked' => (int) ($syncStats->total_checked ?? 0),
                'api_failures' => (int) ($syncStats->api_failures ?? 0),
                'domain_mismatches' => (int) ($syncStats->domain_mismatches ?? 0),
            ],
            'freshness' => [
                'total_active' => (int) $freshness['total_active'],
                'never_verified' => (int) $freshness['never_verified'],
                'verified_within_1h' => (int) $freshness['verified_within_1h'],
                'stale_over_1h' => (int) $freshness['stale_over_1h'],
                'stale_over_24h' => (int) $freshness['stale_over_24h'],
                'last_verified_latest' => $this->toDateTimeString($freshness['last_verified_latest']),
                'last_verified_oldest_active' => $this->toDateTimeString($freshness['last_verified_oldest_active']),
            ],
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('License Health Summary');
        $this->line(sprintf('Window: last %d hour(s) (from %s)', $payload['window_hours'], $payload['window_start']));
        $this->newLine();

        $this->line('verify-licenses');
        $this->table(
            ['Total', 'Success', 'Failed', 'Last Started', 'Last Finished'],
            [[
                $payload['verify_licenses']['total'],
                $payload['verify_licenses']['success'],
                $payload['verify_licenses']['failed'],
                $payload['verify_licenses']['last_started_at'] ?? '--',
                $payload['verify_licenses']['last_finished_at'] ?? '--',
            ]]
        );

        $this->line('license_sync_runs');
        $this->table(
            ['Runs', 'Last Run', 'Total Checked', 'API Failures', 'Domain Mismatches'],
            [[
                $payload['license_sync_runs']['total_runs'],
                $payload['license_sync_runs']['last_run_at'] ?? '--',
                $payload['license_sync_runs']['total_checked'],
                $payload['license_sync_runs']['api_failures'],
                $payload['license_sync_runs']['domain_mismatches'],
            ]]
        );

        $this->line('freshness (active licenses)');
        $this->table(
            ['Total Active', 'Never Verified', 'Within 1h', 'Stale >1h', 'Stale >24h', 'Latest Verified', 'Oldest Active Verified'],
            [[
                $payload['freshness']['total_active'],
                $payload['freshness']['never_verified'],
                $payload['freshness']['verified_within_1h'],
                $payload['freshness']['stale_over_1h'],
                $payload['freshness']['stale_over_24h'],
                $payload['freshness']['last_verified_latest'] ?? '--',
                $payload['freshness']['last_verified_oldest_active'] ?? '--',
            ]]
        );

        return self::SUCCESS;
    }

    private function toDateTimeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toDateTimeString();
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }
}
