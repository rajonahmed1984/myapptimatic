<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\License;
use App\Models\Setting;
use App\Models\StatusAuditLog;
use App\Models\Subscription;
use App\Services\BillingService;
use App\Services\LicenseLifecycleService;
use App\Support\SystemLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * One-off repair for data written before three billing fixes:
 *
 *  - non-monthly renewals had next_invoice_at set near the END of the next
 *    term, so the renewal was invoiced about a full term late;
 *  - paying an invoice pushed licenses one term past what was billed;
 *  - subscriptions created from the admin form defaulted to auto_renew=off,
 *    which the billing cycle treats as "cancel at period end".
 *
 * Reports by default. --apply re-dates renewals; shortening licenses and
 * re-enabling auto-renew each need their own flag as well, because both can
 * undo something an admin set on purpose.
 */
class RepairSubscriptionBillingDrift extends Command
{
    protected $signature = 'subscriptions:repair-billing-drift
                            {--apply : Write the next_invoice_at corrections}
                            {--shorten-licenses : With --apply, also pull over-extended license expiry dates back}
                            {--enable-auto-renew= : With --apply, comma-separated subscription IDs to switch auto-renew back on}';

    protected $description = 'Report and repair renewal dates, license expiry and auto-renew left wrong by earlier billing bugs.';

    public function __construct(
        private BillingService $billing,
        private LicenseLifecycleService $lifecycle
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $today = Carbon::today();

        $this->info($apply ? 'Applying repairs...' : 'Dry run — nothing will be written. Re-run with --apply to fix.');

        $renewals = $this->repairRenewalDates($apply, $today);
        $licenses = $this->repairLicenseExpiry($apply && (bool) $this->option('shorten-licenses'));
        $autoRenew = $this->repairAutoRenew($apply);

        $this->newLine();
        $this->info(sprintf(
            'Renewal dates: %d. Over-extended licenses: %d. Subscriptions with auto-renew off: %d.',
            $renewals,
            $licenses,
            $autoRenew
        ));

        if ($apply) {
            SystemLogger::write('module', 'Subscription billing drift repair completed.', [
                'renewal_dates' => $renewals,
                'licenses_shortened' => $this->option('shorten-licenses') ? $licenses : 0,
                'auto_renew_enabled' => $this->option('enable-auto-renew'),
            ]);
        }

        return self::SUCCESS;
    }

    /**
     * Renewing, non-monthly subscriptions whose next invoice is dated later
     * than the corrected rule would date it, and whose current term has not
     * been billed yet.
     *
     * Only rows that carry the bug's exact fingerprint are changed: a window
     * one full term long, and next_invoice_at where the old code put it. Any
     * other late date — a plan switched from monthly, a date set by hand —
     * is listed for review, because re-dating it would raise a full-term
     * invoice nobody asked for.
     */
    private function repairRenewalDates(bool $apply, Carbon $today): int
    {
        $this->newLine();
        $this->line('<comment>Renewal invoice dates</comment>');
        $count = 0;
        $review = [];

        Subscription::query()
            ->with('plan')
            ->whereIn('status', ['active', 'suspended'])
            ->where('auto_renew', true)
            ->where('cancel_at_period_end', false)
            ->whereHas('plan', fn ($query) => $query->where('interval', '!=', 'monthly'))
            ->orderBy('id')
            ->chunkById(200, function ($subscriptions) use ($apply, $today, &$count, &$review) {
                foreach ($subscriptions as $subscription) {
                    if (! $subscription->current_period_start || ! $subscription->current_period_end || ! $subscription->next_invoice_at) {
                        continue;
                    }

                    $periodStart = $subscription->current_period_start->copy();
                    $periodEnd = $subscription->current_period_end->copy();

                    if ($this->periodIsBilled($subscription, $periodStart, $periodEnd)) {
                        continue;
                    }

                    $expected = $this->billing->nextInvoiceAt($periodStart, $periodEnd, $today, (string) $subscription->plan->interval);

                    if (! $subscription->next_invoice_at->greaterThan($expected)) {
                        continue;
                    }

                    $interval = (string) $subscription->plan->interval;

                    if (! $this->hasDriftFingerprint($subscription, $interval)) {
                        $review[] = sprintf(
                            '  subscription #%d (%s plan): window %s..%s, next_invoice_at %s',
                            $subscription->id,
                            $interval,
                            $periodStart->toDateString(),
                            $periodEnd->toDateString(),
                            $subscription->next_invoice_at->toDateString()
                        );
                        continue;
                    }

                    $count++;
                    $this->line(sprintf(
                        '  subscription #%d (%s): next_invoice_at %s → %s',
                        $subscription->id,
                        $subscription->plan->interval,
                        $subscription->next_invoice_at->toDateString(),
                        $expected->toDateString()
                    ));

                    if ($expected->lessThanOrEqualTo($today)) {
                        $this->line('    term already started — the next billing run will invoice it');
                    }

                    if ($apply) {
                        $subscription->update(['next_invoice_at' => $expected->toDateString()]);
                    }
                }
            });

        if ($review !== []) {
            $this->newLine();
            $this->line('<comment>Needs manual review — left unchanged</comment>');
            $this->line('  The billing window does not fit the plan, or the next invoice date was not set by');
            $this->line('  the old renewal rule (e.g. the plan was switched from monthly, or the date was set');
            $this->line('  by hand). Fix these from the subscription edit page.');

            foreach ($review as $line) {
                $this->line($line);
            }
        }

        return $count;
    }

    /**
     * True when the row looks exactly like the old renewal rule left it: the
     * window spans one full term, and next_invoice_at sits at the window's
     * end, or invoice_generation_days before it.
     */
    private function hasDriftFingerprint(Subscription $subscription, string $interval): bool
    {
        $start = $subscription->current_period_start;
        $end = $subscription->current_period_end;

        $fullTermEnd = match ($interval) {
            'yearly' => $start->copy()->addYear(),
            'quarterly' => $start->copy()->addMonths(3),
            default => $start->copy()->addMonth(),
        };

        if (! $end->isSameDay($fullTermEnd)) {
            return false;
        }

        $generationDays = (int) Setting::getValue('invoice_generation_days');
        $nextInvoiceAt = $subscription->next_invoice_at;

        return $nextInvoiceAt->isSameDay($end)
            || ($generationDays > 0 && $nextInvoiceAt->isSameDay($end->copy()->subDays($generationDays)));
    }

    /**
     * Licenses that run past the period their subscription has paid for plus
     * grace. Only subscriptions whose latest paid invoice records its period
     * are considered; without it there is nothing reliable to compare with.
     */
    private function repairLicenseExpiry(bool $apply): int
    {
        $this->newLine();
        $this->line('<comment>License expiry dates</comment>');
        $count = 0;
        $graceDays = max(0, (int) Setting::getValue('license_expiry_grace_days', LicenseLifecycleService::DEFAULT_EXPIRY_GRACE_DAYS));

        Subscription::query()
            ->with('licenses')
            ->whereIn('status', ['active', 'suspended'])
            ->orderBy('id')
            ->chunkById(200, function ($subscriptions) use ($apply, $graceDays, &$count) {
                foreach ($subscriptions as $subscription) {
                    $latestPaid = Invoice::query()
                        ->where('subscription_id', $subscription->id)
                        ->where('status', 'paid')
                        ->orderByDesc('issue_date')
                        ->orderByDesc('id')
                        ->first(['id', 'period_end']);

                    if (! $latestPaid?->period_end) {
                        continue;
                    }

                    $target = $this->lifecycle->paidThroughDate($subscription)?->addDays($graceDays);

                    if (! $target) {
                        continue;
                    }

                    $overExtended = $subscription->licenses->filter(
                        fn (License $license) => $license->expires_at !== null
                            && $license->expires_at->greaterThan($target)
                    );

                    foreach ($overExtended as $license) {
                        $count++;
                        $this->line(sprintf(
                            '  license #%d (subscription #%d): expires_at %s → %s',
                            $license->id,
                            $subscription->id,
                            $license->expires_at->toDateString(),
                            $target->toDateString()
                        ));

                        if (! $apply) {
                            continue;
                        }

                        $previous = $license->expires_at->toDateString();
                        $license->update(['expires_at' => $target->toDateString()]);

                        StatusAuditLog::logChange(
                            License::class,
                            $license->id,
                            'expires:'.$previous,
                            'expires:'.$target->toDateString(),
                            'expiry_drift_repair'
                        );
                    }
                }
            });

        if ($count > 0 && ! ($this->option('apply') && $this->option('shorten-licenses'))) {
            $this->line('  Check these for expiry dates set by hand, then add --apply --shorten-licenses.');
        }

        return $count;
    }

    /**
     * Live subscriptions set to stop at period end only through auto_renew,
     * which is what the old admin form saved by default. There is no way to
     * tell an accident from a deliberate choice, so only the IDs passed in
     * --enable-auto-renew are changed.
     */
    private function repairAutoRenew(bool $apply): int
    {
        $this->newLine();
        $this->line('<comment>Subscriptions with auto-renew off</comment>');

        $subscriptions = Subscription::query()
            ->with(['customer:id,name', 'plan:id,name'])
            ->whereIn('status', ['active', 'suspended'])
            ->where('auto_renew', false)
            ->where('cancel_at_period_end', false)
            ->orderBy('id')
            ->get();

        foreach ($subscriptions as $subscription) {
            $this->line(sprintf(
                '  subscription #%d — %s (%s), term ends %s',
                $subscription->id,
                $subscription->customer?->name ?? 'Unknown customer',
                $subscription->plan?->name ?? 'Unknown plan',
                $subscription->current_period_end?->toDateString() ?? '--'
            ));
        }

        $ids = collect(explode(',', (string) $this->option('enable-auto-renew')))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->intersect($subscriptions->pluck('id'))
            ->values();

        if ($subscriptions->isNotEmpty() && $ids->isEmpty()) {
            $this->line('  These will be cancelled at term end. To keep any, pass --apply --enable-auto-renew=ID,ID');
        }

        if ($apply && $ids->isNotEmpty()) {
            Subscription::query()->whereIn('id', $ids)->update(['auto_renew' => true]);
            $this->line('  auto-renew switched on for #'.$ids->implode(', #'));
        }

        return $subscriptions->count();
    }

    private function periodIsBilled(Subscription $subscription, Carbon $periodStart, Carbon $periodEnd): bool
    {
        return Invoice::query()
            ->where('subscription_id', $subscription->id)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->where(function ($query) use ($periodStart, $periodEnd) {
                $query->whereDate('period_start', $periodStart->toDateString())
                    ->orWhere(function ($legacy) use ($periodStart, $periodEnd) {
                        $legacy->whereDate('issue_date', '>=', $periodStart->toDateString())
                            ->whereDate('issue_date', '<=', $periodEnd->toDateString());
                    });
            })
            ->exists();
    }
}
