<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SupportTicket;
use App\Models\StatusAuditLog;
use App\Support\SystemLogger;
use Carbon\Carbon;
use App\Events\InvoiceOverdue;
use App\Events\SubscriptionSuspended;

class StatusUpdateService
{
    /**
     * Update invoice status from unpaid to overdue based on due date
     */
    public function updateInvoiceOverdueStatus(Carbon $today = null): int
    {
        $today = $today ?? Carbon::today();
        $count = 0;

        $invoices = Invoice::query()
            ->where('status', 'unpaid')
            ->whereDate('due_date', '<', $today)
            ->get();

        foreach ($invoices as $invoice) {
            $previousStatus = $invoice->status;
            $invoice->update([
                'status' => 'overdue',
                'overdue_at' => $invoice->overdue_at ?? Carbon::now(),
            ]);

            StatusAuditLog::logChange(
                Invoice::class,
                $invoice->id,
                $previousStatus,
                'overdue',
                'auto_overdue'
            );

            InvoiceOverdue::dispatch($invoice);

            SystemLogger::write('activity', 'Invoice marked as overdue automatically.', [
                'invoice_id' => $invoice->id,
                'due_date' => $invoice->due_date,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Update subscription status based on billing activity
     * Suspends subscriptions with overdue invoices
     */
    public function updateSubscriptionSuspensionStatus(Carbon $today = null): int
    {
        if (!Setting::getValue('enable_suspension')) {
            return 0;
        }

        $today = $today ?? Carbon::today();
        $count = 0;
        $suspendDays = (int) Setting::getValue('suspend_days');
        $targetDate = $today->copy()->subDays($suspendDays);

        $invoices = Invoice::query()
            ->with(['subscription.licenses', 'subscription.customer'])
            ->whereNotNull('subscription_id')
            ->whereIn('status', ['unpaid', 'overdue'])
            ->whereDate('due_date', '<=', $targetDate)
            ->get();

        foreach ($invoices as $invoice) {
            $subscription = $invoice->subscription;

            if (!$subscription || $subscription->status === 'cancelled') {
                continue;
            }

            if ($subscription->customer && $subscription->customer->access_override_until && $subscription->customer->access_override_until->isFuture()) {
                continue;
            }

            if ($subscription->status !== 'suspended') {
                $previousStatus = $subscription->status;
                $licenseIds = $subscription->licenses()
                    ->where('status', 'active')
                    ->pluck('id');

                $subscription->update(['status' => 'suspended']);

                StatusAuditLog::logChange(
                    Subscription::class,
                    $subscription->id,
                    $previousStatus,
                    'suspended',
                    'auto_suspend'
                );

                SubscriptionSuspended::dispatch($subscription);

                SystemLogger::write('activity', 'Subscription suspended automatically due to overdue invoice.', [
                    'subscription_id' => $subscription->id,
                    'invoice_id' => $invoice->id,
                    'days_overdue' => $suspendDays,
                ]);

                $count++;
            }

            // Also suspend associated licenses
            $affected = $subscription->licenses()
                ->where('status', 'active')
                ->get(['id', 'status']);

            $subscription->licenses()
                ->where('status', 'active')
                ->update(['status' => 'suspended']);

            foreach ($affected as $license) {
                StatusAuditLog::logChange(
                    License::class,
                    $license->id,
                    $license->status,
                    'suspended',
                    'auto_suspend'
                );
            }
        }

        return $count;
    }

    /**
     * Update subscription and license status based on unsuspension rules
     * Unsuspends when all invoices are paid
     */
    public function updateSubscriptionUnsuspensionStatus(): int
    {
        if (!Setting::getValue('enable_unsuspension')) {
            return 0;
        }

        $count = 0;
        $subscriptions = Subscription::query()
            ->with(['licenses'])
            ->where('status', 'suspended')
            ->get();

        foreach ($subscriptions as $subscription) {
            $latestInvoice = Invoice::query()
                ->where('subscription_id', $subscription->id)
                ->orderByDesc('due_date')
                ->first();

            if ($latestInvoice && $latestInvoice->status !== 'paid') {
                continue;
            }

            $openInvoices = Invoice::query()
                ->where('subscription_id', $subscription->id)
                ->whereIn('status', ['unpaid', 'overdue'])
                ->exists();

            if ($openInvoices) {
                continue;
            }

            $subscription->update(['status' => 'active']);

            SystemLogger::write('activity', 'Subscription unsuspended - all invoices are paid.', [
                'subscription_id' => $subscription->id,
            ]);

            StatusAuditLog::logChange(
                Subscription::class,
                $subscription->id,
                'suspended',
                'active',
                'auto_unsuspend'
            );

            // Also unsuspend associated licenses
            $subscription->licenses()
                ->where('status', 'suspended')
                ->update(['status' => 'active']);

            $count++;
        }

        return $count;
    }

    /**
     * Update subscription status based on termination rules
     * Terminates subscriptions with old overdue invoices
     */
    public function updateSubscriptionTerminationStatus(Carbon $today = null): int
    {
        if (!Setting::getValue('enable_termination')) {
            return 0;
        }

        $today = $today ?? Carbon::today();
        $count = 0;
        $terminationDays = (int) Setting::getValue('termination_days');
        $targetDate = $today->copy()->subDays($terminationDays);

        $invoices = Invoice::query()
            ->with(['subscription.licenses'])
            ->whereNotNull('subscription_id')
            ->whereIn('status', ['unpaid', 'overdue'])
            ->whereDate('due_date', '<=', $targetDate)
            ->get();

        foreach ($invoices as $invoice) {
            $subscription = $invoice->subscription;

            if (!$subscription || $subscription->status === 'cancelled') {
                continue;
            }

            $previousStatus = $subscription->status;
            $subscription->update([
                'status' => 'cancelled',
                'auto_renew' => false,
                'cancelled_at' => Carbon::now(),
            ]);

            StatusAuditLog::logChange(
                Subscription::class,
                $subscription->id,
                $previousStatus,
                'cancelled',
                'auto_termination'
            );

            SystemLogger::write('activity', 'Subscription terminated automatically due to old overdue invoice.', [
                'subscription_id' => $subscription->id,
                'invoice_id' => $invoice->id,
                'days_overdue' => $terminationDays,
            ]);

            // Also revoke associated licenses
            $licenseStatuses = $subscription->licenses()
                ->whereIn('status', ['active', 'suspended'])
                ->pluck('status', 'id');

            $subscription->licenses()
                ->whereIn('status', ['active', 'suspended'])
                ->update(['status' => 'revoked']);

            foreach ($licenseStatuses as $id => $oldStatus) {
                StatusAuditLog::logChange(
                    License::class,
                    $id,
                    $oldStatus,
                    'revoked',
                    'auto_termination'
                );
            }

            $count++;
        }

        return $count;
    }

    /**
     * Update customer status based on subscription activity
     * Activates customer if they have active subscription
     * Deactivates customer if they have no active subscriptions
     */
    public function updateCustomerStatus(): int
    {
        $activated = Customer::query()
            ->where('status', 'inactive')
            ->whereHas('subscriptions', function ($query) {
                $query->where('status', 'active');
            })
            ->update(['status' => 'active']);

        if ($activated > 0) {
            SystemLogger::write('activity', "Customer status updated: $activated customers activated.");
        }

        $deactivated = Customer::query()
            ->where('status', 'active')
            ->whereDoesntHave('subscriptions', function ($query) {
                $query->where('status', 'active');
            })
            ->update(['status' => 'inactive']);

        if ($deactivated > 0) {
            SystemLogger::write('activity', "Customer status updated: $deactivated customers deactivated.");
        }

        return $activated + $deactivated;
    }

    /**
     * Update license expiry status
     * Marks licenses as expired if expiration date has passed
     */
    public function updateLicenseExpiryStatus(Carbon $today = null): int
    {
        $today = $today ?? Carbon::today();
        $count = 0;

        $licenses = License::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', $today)
            ->get();

        foreach ($licenses as $license) {
            $previousStatus = $license->status;
            $license->update(['status' => 'revoked']);

            StatusAuditLog::logChange(
                License::class,
                $license->id,
                $previousStatus,
                'revoked',
                'auto_expired'
            );

            SystemLogger::write('activity', 'License revoked due to expiration.', [
                'license_id' => $license->id,
                'expired_at' => $license->expires_at,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Update support ticket status
     * Auto-closes tickets that haven't been replied to in specified days
     */
    public function updateSupportTicketAutoCloseStatus(Carbon $today = null): int
    {
        if (!Setting::getValue('ticket_auto_close')) {
            return 0;
        }

        $today = $today ?? Carbon::today();
        $count = 0;
        $autoCloseDays = (int) Setting::getValue('ticket_auto_close_days');
        $targetDate = $today->copy()->subDays($autoCloseDays);

        $tickets = SupportTicket::query()
            ->where('status', 'open')
            ->whereNotNull('last_reply_at')
            ->whereDate('last_reply_at', '<=', $targetDate)
            ->get();

        foreach ($tickets as $ticket) {
            $ticket->update([
                'status' => 'closed',
                'auto_closed_at' => Carbon::now(),
            ]);

            SystemLogger::write('activity', 'Support ticket auto-closed due to inactivity.', [
                'ticket_id' => $ticket->id,
                'last_reply_at' => $ticket->last_reply_at,
                'days_inactive' => $autoCloseDays,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Run all status updates
     * Returns array of metrics showing what was updated
     */
    public function updateAllStatuses(Carbon $today = null): array
    {
        $today = $today ?? Carbon::today();
        $metrics = [];

        try {
            $metrics['invoices_overdue'] = $this->updateInvoiceOverdueStatus($today);
            $metrics['subscriptions_suspended'] = $this->updateSubscriptionSuspensionStatus($today);
            $metrics['subscriptions_unsuspended'] = $this->updateSubscriptionUnsuspensionStatus();
            $metrics['subscriptions_terminated'] = $this->updateSubscriptionTerminationStatus($today);
            $metrics['customers_updated'] = $this->updateCustomerStatus();
            $metrics['licenses_expired'] = $this->updateLicenseExpiryStatus($today);
            $metrics['tickets_auto_closed'] = $this->updateSupportTicketAutoCloseStatus($today);

            SystemLogger::write('module', 'All status updates completed.', $metrics);
        } catch (\Throwable $e) {
            SystemLogger::write('module', 'Status update failed: ' . $e->getMessage(), [], level: 'error');
            throw $e;
        }

        return $metrics;
    }

    /**
     * Get status summary for dashboard/automation status page
     */
    public function getStatusSummary(): array
    {
        return [
            'overdue_invoices' => Invoice::where('status', 'overdue')->count(),
            'unpaid_invoices' => Invoice::where('status', 'unpaid')->count(),
            'suspended_subscriptions' => Subscription::where('status', 'suspended')->count(),
            'cancelled_subscriptions' => Subscription::where('status', 'cancelled')->count(),
            'inactive_customers' => Customer::where('status', 'inactive')->count(),
            'suspended_licenses' => License::where('status', 'suspended')->count(),
            'revoked_licenses' => License::where('status', 'revoked')->count(),
            'open_support_tickets' => SupportTicket::where('status', 'open')->count(),
        ];
    }
}
