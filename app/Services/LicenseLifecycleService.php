<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\License;
use App\Models\Setting;
use App\Models\StatusAuditLog;
use App\Models\Subscription;
use App\Support\SystemLogger;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Owns the license side of the billing lifecycle: how far a license is paid up
 * for, and when it may come back after being suspended or expired.
 *
 * Before this existed the renewal never moved `licenses.expires_at`, so a
 * customer who paid every invoice on time still had the license revoked one
 * period after signup. Every payment path now funnels through here.
 */
class LicenseLifecycleService
{
    /**
     * Statuses a license can be restored from once the account is settled.
     * `revoked` is deliberately excluded — that is an admin/termination
     * decision and only an admin may undo it.
     */
    public const RESTORABLE_STATUSES = ['suspended', 'expired'];

    public const DEFAULT_EXPIRY_GRACE_DAYS = 5;

    /**
     * Audit reason an admin's own suspension is recorded under. A license held
     * this way stays suspended until an admin lifts it — paying an invoice or
     * re-activating the subscription must not undo a deliberate hold.
     */
    public const ADMIN_HOLD_REASON = 'admin_suspend';

    /**
     * Push every license on the subscription out to the period the customer
     * has now paid for. Lifetime licenses (no expiry) are left alone.
     */
    public function extendForSubscription(Subscription $subscription, ?Carbon $paidThrough = null): int
    {
        $paidThrough = $paidThrough ?? $this->paidThroughDate($subscription);

        if (! $paidThrough) {
            return 0;
        }

        // Defaults to the same 5 days customers get to settle an invoice, so a
        // renewal paid inside that window never finds the license expired.
        $graceDays = max(0, (int) Setting::getValue('license_expiry_grace_days', self::DEFAULT_EXPIRY_GRACE_DAYS));
        $target = $paidThrough->copy()->startOfDay()->addDays($graceDays);

        $licenses = $subscription->licenses()
            ->whereNotNull('expires_at')
            ->get(['id', 'expires_at']);

        $extended = 0;

        foreach ($licenses as $license) {
            if ($license->expires_at && $license->expires_at->greaterThanOrEqualTo($target)) {
                continue;
            }

            $previous = $license->expires_at?->toDateString();

            License::query()
                ->whereKey($license->id)
                ->update(['expires_at' => $target->toDateString()]);

            SystemLogger::write('activity', 'License expiry extended after payment.', [
                'license_id' => $license->id,
                'subscription_id' => $subscription->id,
                'previous_expires_at' => $previous,
                'expires_at' => $target->toDateString(),
            ]);

            $extended++;
        }

        return $extended;
    }

    /**
     * Bring suspended/expired licenses back once the subscription carries no
     * outstanding balance. Keyed on the balance, never on the subscription's
     * own status — a license can be suspended while the subscription is still
     * active, and that case used to have no way back.
     */
    public function restoreForSubscription(Subscription $subscription, string $reason = 'payment_received'): int
    {
        if ($this->hasOutstandingBalance($subscription)) {
            return 0;
        }

        $today = Carbon::today();

        return $this->restoreLicenses($subscription, self::RESTORABLE_STATUSES, $reason);
    }

    /**
     * Put the subscription's active licenses on hold alongside it, so the
     * license list agrees with what verification already enforces.
     */
    public function suspendForSubscription(Subscription $subscription, string $reason, ?int $actorId = null): int
    {
        $licenses = $subscription->licenses()
            ->where('status', 'active')
            ->get();

        foreach ($licenses as $license) {
            // One save per license so model observers (MyBuilding sync) fire.
            $license->forceFill(['status' => 'suspended'])->save();

            StatusAuditLog::logChange(License::class, $license->id, 'active', 'suspended', $reason, $actorId);
        }

        return $licenses->count();
    }

    /**
     * Lift the suspensions that came with the subscription's own, when an admin
     * makes it active again. Expired licenses still need a paid renewal, and
     * licenses an admin held individually stay held.
     */
    public function restoreAfterAdminActivation(Subscription $subscription, ?int $actorId = null): int
    {
        return $this->restoreLicenses($subscription, ['suspended'], 'admin_subscription_activate', $actorId);
    }

    /**
     * IDs among $licenseIds whose current suspension is an admin hold.
     *
     * @param  iterable<int>  $licenseIds
     * @return array<int, int>
     */
    public function adminHeldLicenseIds(iterable $licenseIds): array
    {
        $ids = collect($licenseIds)->map(fn ($id) => (int) $id)->filter()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        // Latest move into `suspended` per license decides why it is suspended.
        return StatusAuditLog::query()
            ->where('model_type', License::class)
            ->whereIn('model_id', $ids)
            ->where('new_status', 'suspended')
            ->orderBy('id')
            ->get(['model_id', 'reason'])
            ->groupBy('model_id')
            ->filter(fn ($rows) => $rows->last()->reason === self::ADMIN_HOLD_REASON)
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $statuses
     */
    private function restoreLicenses(Subscription $subscription, array $statuses, string $reason, ?int $actorId = null): int
    {
        $today = Carbon::today();

        $licenses = $subscription->licenses()
            ->whereIn('status', $statuses)
            ->get();

        if ($licenses->isEmpty()) {
            return 0;
        }

        $held = $this->adminHeldLicenseIds($licenses->where('status', 'suspended')->pluck('id'));

        // A license whose paid-through date is still in the past must not be
        // flipped back to active — extendForSubscription() runs first and will
        // have moved it if the payment actually covers the current period.
        $eligible = $licenses->filter(function (License $license) use ($today, $held) {
            if (in_array((int) $license->id, $held, true)) {
                return false;
            }

            return $license->expires_at === null
                || $license->expires_at->greaterThanOrEqualTo($today);
        });

        if ($eligible->isEmpty()) {
            return 0;
        }

        foreach ($eligible as $license) {
            $previous = (string) $license->status;
            $license->forceFill(['status' => 'active'])->save();

            StatusAuditLog::logChange(License::class, $license->id, $previous, 'active', $reason, $actorId);
        }

        SystemLogger::write('activity', 'Licenses reactivated.', [
            'subscription_id' => $subscription->id,
            'license_ids' => $eligible->pluck('id')->values()->all(),
            'reason' => $reason,
        ]);

        return $eligible->count();
    }

    /**
     * True when the subscription still has money owed on it, counting partial
     * payments and credits rather than the invoice status alone.
     */
    public function hasOutstandingBalance(Subscription $subscription): bool
    {
        return Invoice::query()
            ->where('subscription_id', $subscription->id)
            ->whereIn('status', ['unpaid', 'overdue'])
            ->whereRaw($this->outstandingBalanceSql())
            ->exists();
    }

    /**
     * The same balance test for a whole customer, used to decide whether the
     * portal access block can be lifted.
     */
    public function customerHasOutstandingBalance(int $customerId): bool
    {
        return Invoice::query()
            ->where('customer_id', $customerId)
            ->whereIn('status', ['unpaid', 'overdue'])
            ->whereRaw($this->outstandingBalanceSql())
            ->exists();
    }

    /**
     * How far the customer has actually paid: the end of the latest period a
     * paid invoice covers. Falls back to the subscription window only when the
     * latest paid invoice carries no period of its own.
     */
    public function paidThroughDate(Subscription $subscription): ?Carbon
    {
        $paidInvoice = Invoice::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', 'paid')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->first(['id', 'issue_date', 'period_end']);

        if (! $paidInvoice) {
            return null;
        }

        // The invoice records the period it billed. The subscription window is
        // no guide here: generateInvoiceForSubscription() rolls it on to the
        // next, still unpaid, term as soon as the invoice is issued.
        if ($paidInvoice->period_end) {
            $paidThrough = Invoice::query()
                ->where('subscription_id', $subscription->id)
                ->where('status', 'paid')
                ->max('period_end');

            return Carbon::parse($paidThrough)->startOfDay();
        }

        // Legacy and manually raised invoices carry no period.
        $periodEnd = $subscription->current_period_end
            ? Carbon::parse($subscription->current_period_end)
            : null;

        if ($periodEnd) {
            return $periodEnd->copy()->startOfDay();
        }

        $interval = (string) ($subscription->plan?->interval ?? 'monthly');
        $issueDate = Carbon::parse($paidInvoice->issue_date);

        return match ($interval) {
            'yearly' => $issueDate->copy()->addYear(),
            'quarterly' => $issueDate->copy()->addMonths(3),
            default => $issueDate->copy()->addMonth(),
        };
    }

    /**
     * Licenses that automation is allowed to suspend right now.
     */
    public function suspendableLicenses(Subscription $subscription, ?Carbon $today = null): Collection
    {
        $today = $today ?? Carbon::today();

        return $subscription->licenses()
            ->where('status', 'active')
            ->where(function ($query) use ($today) {
                $query->whereNull('auto_suspend_override_until')
                    ->orWhereDate('auto_suspend_override_until', '<', $today->toDateString());
            })
            ->get(['id', 'status']);
    }

    private function outstandingBalanceSql(): string
    {
        return "(COALESCE(invoices.total, 0) - COALESCE((SELECT SUM(CASE WHEN type IN ('payment', 'credit') THEN amount ELSE 0 END) FROM accounting_entries WHERE accounting_entries.invoice_id = invoices.id), 0)) > 0.009";
    }
}
