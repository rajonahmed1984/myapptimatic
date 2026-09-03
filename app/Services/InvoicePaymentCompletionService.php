<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\StatusAuditLog;
use App\Support\SystemLogger;
use Carbon\Carbon;

/**
 * The single place that decides what happens once an invoice is settled.
 *
 * This used to be copy-pasted across four entry points — gateway callback,
 * payment-proof approval, admin "mark paid" and admin "add payment" — and the
 * four copies had drifted: only two created commission earnings, only two
 * checked for other open invoices, and none of them ever touched the license
 * expiry date. Everything routes through here now so those paths cannot
 * disagree again.
 */
class InvoicePaymentCompletionService
{
    public function __construct(
        private LicenseLifecycleService $licenseLifecycle,
        private StatusUpdateService $statusUpdates,
        private CommissionService $commissions,
        private AdminNotificationService $adminNotifications,
        private ClientNotificationService $clientNotifications,
        private SalesRepNotificationService $salesRepNotifications,
    ) {
    }

    /**
     * Flip the invoice to paid (when it isn't already) and run every downstream
     * consequence. Safe to call twice — the state changes are all idempotent.
     *
     * @param  array{reason?: string, reference?: string|null, actor_id?: int|null, notify?: bool}  $options
     */
    public function complete(Invoice $invoice, array $options = []): array
    {
        $reason = (string) ($options['reason'] ?? 'payment_received');
        $reference = $options['reference'] ?? null;
        $actorId = $options['actor_id'] ?? null;
        $notify = (bool) ($options['notify'] ?? true);

        $wasPaid = (string) $invoice->status === 'paid';

        if (! $wasPaid) {
            $previousStatus = (string) $invoice->status;

            $invoice->update([
                'status' => 'paid',
                'paid_at' => $invoice->paid_at ?? Carbon::now(),
                'overdue_at' => null,
            ]);

            StatusAuditLog::logChange(
                Invoice::class,
                $invoice->id,
                $previousStatus,
                'paid',
                $reason,
                $actorId
            );
        }

        $result = [
            'was_already_paid' => $wasPaid,
            'licenses_extended' => 0,
            'licenses_restored' => 0,
            'subscription_unsuspended' => false,
            'access_block_cleared' => false,
            'commission_earning_id' => null,
        ];

        $invoice->loadMissing('subscription');
        $subscription = $invoice->subscription;

        if ($subscription) {
            // Order matters: push the paid-through date out first, so the
            // restore step does not refuse a license that is only "expired"
            // because this very payment had not been applied yet.
            $result['licenses_extended'] = $this->licenseLifecycle->extendForSubscription($subscription->fresh());
            $result['licenses_restored'] = $this->licenseLifecycle->restoreForSubscription($subscription->fresh(), $reason);
            $result['subscription_unsuspended'] = $this->statusUpdates
                ->unsuspendSubscriptionIfEligible($subscription->fresh());
        }

        if ($invoice->customer_id
            && ! $this->licenseLifecycle->customerHasOutstandingBalance((int) $invoice->customer_id)) {
            Customer::query()
                ->where('id', $invoice->customer_id)
                ->whereNotNull('access_override_until')
                ->update(['access_override_until' => null]);

            $result['access_block_cleared'] = true;
        }

        $result['commission_earning_id'] = $this->recordCommission($invoice);

        if ($notify && ! $wasPaid) {
            $this->notify($invoice, $reference);
        }

        SystemLogger::write('activity', 'Invoice settled.', array_merge([
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'subscription_id' => $invoice->subscription_id,
            'reason' => $reason,
        ], $result), $actorId);

        return $result;
    }

    private function recordCommission(Invoice $invoice): ?int
    {
        try {
            $earning = $this->commissions
                ->createOrUpdateEarningOnInvoicePaid($invoice->fresh('subscription.customer'));

            return $earning?->id;
        } catch (\Throwable $e) {
            SystemLogger::write('module', 'Commission earning failed on paid invoice.', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ], level: 'error');

            return null;
        }
    }

    private function notify(Invoice $invoice, ?string $reference): void
    {
        $fresh = $invoice->fresh('customer');

        if (! $fresh) {
            return;
        }

        try {
            $this->adminNotifications->sendInvoicePaid($fresh);
        } catch (\Throwable $e) {
            SystemLogger::write('module', 'Admin invoice paid notification failed.', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ], level: 'error');
        }

        try {
            $this->clientNotifications->sendInvoicePaymentStatusNotification($fresh, 'paid', $reference);
        } catch (\Throwable $e) {
            SystemLogger::write('module', 'Client invoice paid notification failed.', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ], level: 'error');
        }

        try {
            $this->salesRepNotifications->sendInvoicePaymentStatusToRelatedSalesReps($fresh, 'paid', $reference);
        } catch (\Throwable $e) {
            SystemLogger::write('module', 'Sales rep invoice paid notification failed.', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ], level: 'error');
        }
    }
}
