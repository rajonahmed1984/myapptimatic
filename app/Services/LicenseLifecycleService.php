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

        $graceDays = max(0, (int) Setting::getValue('license_expiry_grace_days', 0));
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

        $licenses = $subscription->licenses()
            ->whereIn('status', self::RESTORABLE_STATUSES)
            ->get(['id', 'status', 'expires_at']);

        if ($licenses->isEmpty()) {
            return 0;
        }

        // A license whose paid-through date is still in the past must not be
        // flipped back to active — extendForSubscription() runs first and will
        // have moved it if the payment actually covers the current period.
        $eligible = $licenses->filter(function (License $license) use ($today) {
            return $license->expires_at === null
                || $license->expires_at->greaterThanOrEqualTo($today);
        });

        if ($eligible->isEmpty()) {
            return 0;
        }

        License::query()
            ->whereIn('id', $eligible->pluck('id'))
            ->update(['status' => 'active']);

        foreach ($eligible as $license) {
            StatusAuditLog::logChange(
                License::class,
                $license->id,
                (string) $license->status,
                'active',
                $reason
            );
        }

        SystemLogger::write('activity', 'Licenses reactivated after account settled.', [
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
     * paid invoice covers. Falls back to the subscription window when the
     * invoice carries no period of its own.
     */
    public function paidThroughDate(Subscription $subscription): ?Carbon
    {
        $paidInvoice = Invoice::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', 'paid')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->first(['id', 'issue_date']);

        if (! $paidInvoice) {
            return null;
        }

        $periodEnd = $subscription->current_period_end
            ? Carbon::parse($subscription->current_period_end)
            : null;

        // generateInvoiceForSubscription() rolls the window forward as soon as
        // it issues the invoice, so current_period_end already points at the
        // end of the period this invoice bought.
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
