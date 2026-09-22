<?php

namespace App\Services;

use App\Models\CancellationRequest;
use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

/**
 * Ends a service the way a customer expects it to end.
 *
 * Whichever route is taken, money already owed is left alone: cancelling stops
 * *future* invoices, it never writes off the ones already issued. The licence
 * state reaches a MyBuilding installation on its own, because
 * MyBuildingLicenseSyncObserver watches the subscription's status.
 */
class SubscriptionCancellationService
{
    /**
     * What the service still owes, so a request records the balance it was
     * made against and an admin sees it before deciding.
     */
    public function outstandingFor(Subscription $subscription): float
    {
        $invoices = Invoice::query()
            ->where('subscription_id', $subscription->id)
            ->whereIn('status', ['unpaid', 'overdue'])
            ->withSum(
                ['accountingEntries as collected' => fn ($q) => $q->whereIn('type', ['payment', 'credit'])],
                'amount'
            )
            ->get();

        return round($invoices->sum(
            fn (Invoice $invoice) => max(0, (float) $invoice->total - (float) ($invoice->collected ?? 0))
        ), 2);
    }

    /**
     * Let the paid-for term run out. The billing cycle turns this into a
     * cancelled subscription once current_period_end passes, and issues no
     * invoice in the meantime.
     */
    public function cancelAtPeriodEnd(Subscription $subscription): void
    {
        $subscription->forceFill([
            'cancel_at_period_end' => true,
            'auto_renew' => false,
            'next_invoice_at' => $subscription->current_period_end?->toDateString()
                ?? $subscription->next_invoice_at,
        ])->save();
    }

    /**
     * Stop the service now. Used when the customer asked for immediate
     * cancellation rather than waiting out the period they paid for.
     */
    public function cancelNow(Subscription $subscription): void
    {
        DB::transaction(function () use ($subscription) {
            $subscription->forceFill([
                'status' => 'cancelled',
                'cancel_at_period_end' => false,
                'auto_renew' => false,
                'cancelled_at' => now(),
            ])->save();

            $this->revokeLicenses($subscription);
        });
    }

    /**
     * Take the subscription's licences out of service.
     *
     * Saved one at a time rather than with a mass update so the MyBuilding
     * sync observer sees each change and pushes it.
     */
    public function revokeLicenses(Subscription $subscription): void
    {
        $subscription->loadMissing('licenses');

        foreach ($subscription->licenses as $license) {
            if ($license->status !== 'revoked') {
                $license->forceFill(['status' => 'revoked'])->save();
            }
        }
    }

    /**
     * Apply an accepted request. Returns the wording for the admin flash.
     */
    public function apply(CancellationRequest $cancellationRequest): string
    {
        $subscription = $cancellationRequest->subscription;

        if (! $subscription) {
            return 'Cancellation accepted, but the subscription no longer exists.';
        }

        if ($cancellationRequest->isImmediate()) {
            $this->cancelNow($subscription);

            return 'Service cancelled immediately.';
        }

        $this->cancelAtPeriodEnd($subscription);

        $endsOn = $subscription->current_period_end?->format(config('app.date_format', 'd-m-Y'));

        return $endsOn
            ? "Cancellation scheduled. The service stays active until {$endsOn} and will not be invoiced again."
            : 'Cancellation scheduled for the end of the current billing period.';
    }
}
