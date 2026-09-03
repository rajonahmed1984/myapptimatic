<?php

namespace App\Console\Commands;

use App\Models\License;
use App\Models\StatusAuditLog;
use App\Models\Subscription;
use App\Services\LicenseLifecycleService;
use App\Support\SystemLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * One-off repair for the renewal bug: `licenses.expires_at` was never moved
 * when a renewal was paid, so licenses on fully-settled subscriptions drifted
 * past their expiry and were revoked. This walks every live subscription, pulls
 * the expiry back in line with what the customer has paid for, and reactivates
 * anything that was only shut off because of the drift.
 *
 * Safe to re-run. Start with --dry-run.
 */
class RepairPaidLicenseExpiry extends Command
{
    protected $signature = 'licenses:repair-paid-expiry
                            {--dry-run : Report what would change without writing anything}
                            {--include-revoked : Also reactivate licenses that were revoked by the old expiry sweep}';

    protected $description = 'Re-align license expiry dates with paid subscription periods and restore wrongly-blocked licenses.';

    public function __construct(private LicenseLifecycleService $lifecycle)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $includeRevoked = (bool) $this->option('include-revoked');
        $today = Carbon::today();

        $extended = 0;
        $restored = 0;
        $skipped = 0;

        $this->info($dryRun ? 'Dry run — nothing will be written.' : 'Repairing license expiry dates...');

        Subscription::query()
            ->with(['plan', 'licenses'])
            ->whereIn('status', ['active', 'suspended'])
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use (&$extended, &$restored, &$skipped, $dryRun, $includeRevoked, $today) {
                foreach ($subscriptions as $subscription) {
                    if ($this->lifecycle->hasOutstandingBalance($subscription)) {
                        $skipped++;
                        continue;
                    }

                    $paidThrough = $this->lifecycle->paidThroughDate($subscription);

                    if (! $paidThrough) {
                        $skipped++;
                        continue;
                    }

                    $stale = $subscription->licenses
                        ->filter(fn (License $license) => $license->expires_at !== null
                            && $license->expires_at->lessThan($paidThrough));

                    foreach ($stale as $license) {
                        $this->line(sprintf(
                            '  license #%d: expires_at %s → %s',
                            $license->id,
                            $license->expires_at->toDateString(),
                            $paidThrough->toDateString()
                        ));
                    }

                    if ($dryRun) {
                        $extended += $stale->count();
                        $restored += $this->recoverableLicenses($subscription, $includeRevoked)->count();
                        continue;
                    }

                    $extended += $this->lifecycle->extendForSubscription($subscription, $paidThrough);

                    if ($includeRevoked) {
                        $restored += $this->reactivateRevoked($subscription, $today);
                    }

                    $restored += $this->lifecycle
                        ->restoreForSubscription($subscription->fresh(), 'expiry_repair');
                }
            });

        $this->newLine();
        $this->info(sprintf(
            '%s %d expiry date(s), reactivated %d license(s), skipped %d subscription(s) with an open balance.',
            $dryRun ? 'Would extend' : 'Extended',
            $extended,
            $restored,
            $skipped
        ));

        if (! $dryRun) {
            SystemLogger::write('module', 'Paid license expiry repair completed.', [
                'extended' => $extended,
                'restored' => $restored,
                'skipped' => $skipped,
                'include_revoked' => $includeRevoked,
            ]);
        }

        return self::SUCCESS;
    }

    private function recoverableLicenses(Subscription $subscription, bool $includeRevoked)
    {
        $statuses = LicenseLifecycleService::RESTORABLE_STATUSES;

        if ($includeRevoked) {
            $statuses[] = 'revoked';
        }

        return $subscription->licenses->whereIn('status', $statuses);
    }

    /**
     * Licenses revoked by the old expiry sweep carry an `auto_expired` audit
     * entry. Only those are reversed — a revocation an admin made by hand, or
     * one that came from termination, stays put.
     */
    private function reactivateRevoked(Subscription $subscription, Carbon $today): int
    {
        $count = 0;

        foreach ($subscription->licenses()->where('status', 'revoked')->get() as $license) {
            $wasAutoExpired = StatusAuditLog::query()
                ->where('model_type', License::class)
                ->where('model_id', $license->id)
                ->where('new_status', 'revoked')
                ->where('reason', 'auto_expired')
                ->exists();

            if (! $wasAutoExpired) {
                continue;
            }

            $license->update(['status' => 'expired']);

            StatusAuditLog::logChange(
                License::class,
                $license->id,
                'revoked',
                'expired',
                'expiry_repair'
            );

            $count++;
        }

        return $count;
    }
}
