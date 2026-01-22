<?php

namespace App\Console\Commands;

use App\Models\License;
use App\Models\LicenseDomain;
use App\Models\LicenseSyncRun;
use App\Models\LicenseUsageLog;
use App\Models\Setting;
use App\Models\StatusAuditLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class LogLicenseSyncRun extends Command
{
    protected $signature = 'licenses:sync-log';

    protected $description = 'Log a daily license synchronisation summary for reporting.';

    public function handle(): int
    {
        $timeZone = Setting::getValue('time_zone', config('app.timezone', 'UTC')) ?: 'UTC';
        $now = Carbon::now($timeZone);
        $start = $now->copy()->subDay();

        $usageBase = LicenseUsageLog::query()
            ->whereBetween('created_at', [$start, $now]);

        $totalChecked = (int) (clone $usageBase)->count();
        $apiFailures = (int) (clone $usageBase)->where('decision', 'block')->count();
        $domainMismatches = (int) (clone $usageBase)->where('reason', 'domain_not_allowed')->count();
        $invalidCount = (int) (clone $usageBase)
            ->whereIn('reason', ['license_inactive', 'license_not_found'])
            ->count();

        $statusBase = StatusAuditLog::query()
            ->where('model_type', License::class)
            ->whereBetween('created_at', [$start, $now]);

        $updatedCount = (int) (clone $statusBase)->count();
        $expiredCount = (int) (clone $statusBase)->where('reason', 'auto_expired')->count();
        $suspendedCount = (int) (clone $statusBase)->where('new_status', 'suspended')->count();

        $domainUpdates = (int) LicenseDomain::query()
            ->whereBetween('updated_at', [$start, $now])
            ->count();

        $details = $this->buildDetails($start, $now);

        LicenseSyncRun::create([
            'run_at' => $now->toDateTimeString(),
            'total_checked' => $totalChecked,
            'updated_count' => $updatedCount,
            'expired_count' => $expiredCount,
            'suspended_count' => $suspendedCount,
            'invalid_count' => $invalidCount,
            'domain_updates_count' => $domainUpdates,
            'domain_mismatch_count' => $domainMismatches,
            'api_failures_count' => $apiFailures,
            'failed_count' => $apiFailures,
            'errors_json' => $details,
            'notes' => 'Daily license synchronisation summary.',
        ]);

        return self::SUCCESS;
    }

    private function buildDetails(Carbon $start, Carbon $now): array
    {
        $statusChanges = StatusAuditLog::query()
            ->where('model_type', License::class)
            ->whereBetween('created_at', [$start, $now])
            ->orderByDesc('created_at')
            ->take(10)
            ->get();

        $errorLogs = LicenseUsageLog::query()
            ->whereBetween('created_at', [$start, $now])
            ->where('decision', 'block')
            ->orderByDesc('created_at')
            ->take(10)
            ->get();

        $licenseIds = $statusChanges->pluck('model_id')
            ->merge($errorLogs->pluck('license_id'))
            ->filter()
            ->unique()
            ->values();

        $licenses = License::query()
            ->with(['subscription.customer'])
            ->whereIn('id', $licenseIds)
            ->get()
            ->keyBy('id');

        $details = [];

        foreach ($statusChanges as $change) {
            $license = $licenses->get($change->model_id);
            $details[] = [
                'type' => 'status_change',
                'license_id' => $change->model_id,
                'license_key' => $license?->license_key,
                'customer' => $license?->subscription?->customer?->name,
                'subscription_id' => $license?->subscription_id,
                'previous_status' => $change->old_status,
                'new_status' => $change->new_status,
                'reason' => $change->reason,
                'domain' => null,
                'created_at' => $change->created_at?->toDateTimeString(),
            ];
        }

        foreach ($errorLogs as $log) {
            $license = $licenses->get($log->license_id);
            $details[] = [
                'type' => 'verification_error',
                'license_id' => $log->license_id,
                'license_key' => $license?->license_key,
                'customer' => $license?->subscription?->customer?->name,
                'subscription_id' => $license?->subscription_id,
                'previous_status' => null,
                'new_status' => null,
                'reason' => $log->reason,
                'domain' => $log->domain,
                'created_at' => $log->created_at?->toDateTimeString(),
            ];
        }

        $details = collect($details)
            ->sortByDesc(fn ($item) => $item['created_at'])
            ->take(10)
            ->values()
            ->all();

        return Arr::map($details, function (array $item) {
            $item['created_at'] = $item['created_at'] ?: now()->toDateTimeString();
            return $item;
        });
    }
}
