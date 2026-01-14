<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Setting;
use App\Models\Subscription;
use App\Jobs\SendCronSummaryEmail;
use App\Jobs\SendInvoiceCreatedNotifications;
use App\Jobs\SendInvoiceReminderNotification;
use App\Jobs\SendLicenseExpiryNoticeNotification;
use App\Jobs\SendTicketAdminReminderNotification;
use App\Jobs\SendTicketAutoCloseNotification;
use App\Jobs\SendTicketFeedbackNotification;
use App\Services\BillingService;
use App\Services\MaintenanceBillingService;
use App\Services\StatusUpdateService;
use App\Models\SupportTicket;
use App\Support\SystemLogger;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunBillingCycle extends Command
{
    protected $signature = 'billing:run';

    protected $description = 'Generate invoices, mark overdue, apply late fees, and run automation.';

    public function __construct(
        private BillingService $billingService,
        private MaintenanceBillingService $maintenanceBillingService,
        private StatusUpdateService $statusUpdateService
    )
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $startedAt = Carbon::now();
        Setting::setValue('billing_last_started_at', $startedAt->toDateTimeString());
        Setting::setValue('billing_last_status', 'running');
        Setting::setValue('billing_last_error', '');
        $metrics = $this->emptyMetrics();
        $stepErrors = [];

        SystemLogger::write('module', 'Billing run started.', [
            'started_at' => $startedAt->toDateTimeString(),
        ]);

        try {
            $today = Carbon::today();

            $runStep = function (string $step, callable $fn) use (&$metrics, &$stepErrors) {
                try {
                    return $fn();
                } catch (\Throwable $e) {
                    $stepErrors[$step] = $e->getMessage();
                    SystemLogger::write('module', 'Billing step failed.', [
                        'step' => $step,
                        'error' => $e->getMessage(),
                    ], level: 'error');

                    return null;
                }
            };

            $invoiceMetrics = $runStep('generate_invoices', fn () => $this->generateInvoices($today));
            if ($invoiceMetrics) {
                $metrics['invoices_generated'] = $invoiceMetrics['generated'];
                $metrics['fixed_term_terminations'] = $invoiceMetrics['fixed_term_terminations'];
            }

            $maintenanceMetrics = $runStep('maintenance_invoices', fn () => $this->maintenanceBillingService->generateInvoicesForDueMaintenances($today));
            if ($maintenanceMetrics) {
                $metrics['maintenance_invoices_generated'] = $maintenanceMetrics['generated'];
                $metrics['maintenance_invoices_skipped'] = $maintenanceMetrics['skipped'];
            }

            $metrics['invoices_overdue'] = $runStep('invoices_overdue', fn () => $this->statusUpdateService->updateInvoiceOverdueStatus($today)) ?? 0;
            $metrics['late_fees_added'] = $runStep('late_fees', fn () => $this->applyLateFees($today)) ?? 0;
            $metrics['auto_cancellations'] = $runStep('auto_cancellations', fn () => $this->applyAutoCancellation($today)) ?? 0;
            $metrics['suspensions'] = $runStep('suspensions', fn () => $this->statusUpdateService->updateSubscriptionSuspensionStatus($today)) ?? 0;
            $metrics['terminations'] = $runStep('terminations', fn () => $this->statusUpdateService->updateSubscriptionTerminationStatus($today)) ?? 0;
            $metrics['unsuspensions'] = $runStep('unsuspensions', fn () => $this->statusUpdateService->updateSubscriptionUnsuspensionStatus()) ?? 0;
            $metrics['client_status_updates'] = $runStep('client_status_updates', fn () => $this->statusUpdateService->updateCustomerStatus()) ?? 0;
            $metrics['licenses_expired'] = $runStep('licenses_expired', fn () => $this->statusUpdateService->updateLicenseExpiryStatus($today)) ?? 0;
            $metrics['invoice_reminders_sent'] = $runStep('invoice_reminders', fn () => $this->sendInvoiceReminders($today)) ?? 0;
            $metrics['ticket_auto_closed'] = $runStep('ticket_auto_close', fn () => $this->statusUpdateService->updateSupportTicketAutoCloseStatus($today)) ?? 0;
            $metrics['ticket_admin_reminders'] = $runStep('ticket_admin_reminders', fn () => $this->sendTicketAdminReminders($today)) ?? 0;
            $metrics['ticket_feedback_requests'] = $runStep('ticket_feedback', fn () => $this->sendTicketFeedbackRequests($today)) ?? 0;
            $metrics['license_expiry_notices'] = $runStep('license_expiry_notices', fn () => $this->sendLicenseExpiryNotices($today)) ?? 0;
            $metrics['tickets_deleted'] = $runStep('ticket_cleanup', fn () => $this->cleanupClosedTickets($today)) ?? 0;

            Setting::setValue('billing_last_run_at', Carbon::now()->toDateTimeString());
            Setting::setValue('billing_last_status', 'success');
            Setting::setValue('billing_last_metrics', json_encode($metrics));
            Setting::setValue('billing_last_error', $stepErrors ? json_encode($stepErrors) : '');

            $this->info('Billing run completed.');
            $this->sendCronSummary('success', $metrics, $startedAt, Carbon::now(), null);

            SystemLogger::write('module', 'Billing run completed.', [
                'started_at' => $startedAt->toDateTimeString(),
                'finished_at' => Carbon::now()->toDateTimeString(),
                'metrics' => [
                    'invoices_generated' => $metrics['invoices_generated'] ?? 0,
                    'invoices_overdue' => $metrics['invoices_overdue'] ?? 0,
                    'suspensions' => $metrics['suspensions'] ?? 0,
                    'terminations' => $metrics['terminations'] ?? 0,
                ],
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Setting::setValue('billing_last_run_at', Carbon::now()->toDateTimeString());
            Setting::setValue('billing_last_status', 'failed');
            Setting::setValue('billing_last_error', substr($e->getMessage(), 0, 500));
            Setting::setValue('billing_last_metrics', json_encode($metrics));

            $this->error('Billing run failed: ' . $e->getMessage());
            $this->sendCronSummary('failed', $metrics, $startedAt, Carbon::now(), $e->getMessage());

            SystemLogger::write('module', 'Billing run failed.', [
                'started_at' => $startedAt->toDateTimeString(),
                'error' => $e->getMessage(),
            ], level: 'error');

            return self::FAILURE;
        }
    }

    private function generateInvoices(Carbon $today): array
    {
        $count = 0;
        $fixedTermTerminations = 0;
        Subscription::query()
            ->with(['plan', 'customer'])
            ->where('status', 'active')
            ->whereDate('next_invoice_at', '<=', $today->toDateString())
            ->orderBy('id')
            ->chunkById(200, function ($subscriptions) use (&$count, &$fixedTermTerminations, $today) {
                foreach ($subscriptions as $subscription) {
                    if ($subscription->cancel_at_period_end && $subscription->current_period_end->lessThanOrEqualTo($today)) {
                        $subscription->update([
                            'status' => 'cancelled',
                            'auto_renew' => false,
                        ]);
                        $fixedTermTerminations++;
                        continue;
                    }

                    if ($subscription->cancel_at_period_end && $subscription->current_period_end->greaterThan($today)) {
                        $subscription->update([
                            'next_invoice_at' => $subscription->current_period_end->toDateString(),
                        ]);
                        continue;
                    }

                    $invoice = $this->billingService->generateInvoiceForSubscription($subscription, $today);
                    if ($invoice) {
                        $count++;
                        SendInvoiceCreatedNotifications::dispatch($invoice->id);
                    }
                }
            });

        return [
            'generated' => $count,
            'fixed_term_terminations' => $fixedTermTerminations,
        ];
    }

    private function markOverdue(Carbon $today): int
    {
        $count = 0;
        $invoices = Invoice::query()
            ->where('status', 'unpaid')
            ->whereDate('due_date', '<', $today)
            ->get();

        foreach ($invoices as $invoice) {
            $invoice->update([
                'status' => 'overdue',
                'overdue_at' => $invoice->overdue_at ?? Carbon::now(),
            ]);
            $count++;
        }

        return $count;
    }

    private function applyLateFees(Carbon $today): int
    {
        $count = 0;
        $lateFeeDays = (int) Setting::getValue('late_fee_days');
        $lateFeeAmount = (float) Setting::getValue('late_fee_amount');
        $lateFeeType = Setting::getValue('late_fee_type');
        $now = Carbon::now();

        if ($lateFeeDays <= 0 || $lateFeeAmount <= 0) {
            return $count;
        }

        $targetDate = $today->copy()->subDays($lateFeeDays);

        Invoice::query()
            ->whereIn('status', ['unpaid', 'overdue'])
            ->whereNull('late_fee_applied_at')
            ->whereDate('due_date', '<=', $targetDate->toDateString())
            ->orderBy('id')
            ->chunkById(200, function ($invoices) use (&$count, $lateFeeType, $lateFeeAmount, $now) {
                foreach ($invoices as $invoice) {
                    $lateFee = $lateFeeType === 'percent'
                        ? ($invoice->subtotal * ($lateFeeAmount / 100))
                        : $lateFeeAmount;

                    if ($lateFee <= 0) {
                        continue;
                    }

                    $invoice->update([
                        'late_fee' => $invoice->late_fee + $lateFee,
                        'total' => $invoice->subtotal + $invoice->late_fee + $lateFee,
                        'late_fee_applied_at' => $now,
                    ]);
                    $count++;
                }
            });

        return $count;
    }

    private function applyAutoCancellation(Carbon $today): int
    {
        if (! Setting::getValue('enable_auto_cancellation')) {
            return 0;
        }

        $days = (int) Setting::getValue('auto_cancellation_days');
        $targetDate = $today->copy()->subDays($days);

        return (int) Invoice::query()
            ->whereIn('status', ['unpaid', 'overdue'])
            ->whereDate('due_date', '<=', $targetDate->toDateString())
            ->update(['status' => 'cancelled']);
    }

    private function applySuspensions(Carbon $today): int
    {
        if (! Setting::getValue('enable_suspension')) {
            return 0;
        }

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

            if (! $subscription || $subscription->status === 'cancelled') {
                continue;
            }

            if ($subscription->customer && $subscription->customer->access_override_until && $subscription->customer->access_override_until->isFuture()) {
                continue;
            }

            if ($subscription->status !== 'suspended') {
                $subscription->update(['status' => 'suspended']);
                $count++;
            }

            $subscription->licenses()
                ->where('status', 'active')
                ->update(['status' => 'suspended']);
        }

        return $count;
    }

    private function applyTerminations(Carbon $today): int
    {
        if (! Setting::getValue('enable_termination')) {
            return 0;
        }

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

            if (! $subscription || $subscription->status === 'cancelled') {
                continue;
            }

            $subscription->update([
                'status' => 'cancelled',
                'auto_renew' => false,
                'cancelled_at' => Carbon::now(),
            ]);
            $count++;

            $subscription->licenses()
                ->whereIn('status', ['active', 'suspended'])
                ->update(['status' => 'revoked']);
        }

        return $count;
    }

    private function applyUnsuspensions(): int
    {
        if (! Setting::getValue('enable_unsuspension')) {
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
            $subscription->licenses()
                ->where('status', 'suspended')
                ->update(['status' => 'active']);
            $count++;
        }

        return $count;
    }

    private function updateClientStatuses(): int
    {
        $activated = Customer::query()
            ->where('status', 'inactive')
            ->whereHas('subscriptions', function ($query) {
                $query->where('status', 'active');
            })
            ->update(['status' => 'active']);

        $deactivated = Customer::query()
            ->where('status', 'active')
            ->whereDoesntHave('subscriptions', function ($query) {
                $query->where('status', 'active');
            })
            ->update(['status' => 'inactive']);

        return $activated + $deactivated;
    }

    private function sendInvoiceReminders(Carbon $today): int
    {
        if (! Setting::getValue('payment_reminder_emails')) {
            return 0;
        }

        $sent = 0;

        $unpaidDays = (int) Setting::getValue('invoice_unpaid_reminder_days');
        if ($unpaidDays > 0) {
            $targetDate = $today->copy()->addDays($unpaidDays);
            $sent += $this->sendReminderBatch(
                $targetDate,
                'invoice_payment_reminder',
                'reminder_sent_at',
                ['unpaid']
            );
        }

        $firstDays = (int) Setting::getValue('first_overdue_reminder_days');
        if ($firstDays > 0) {
            $targetDate = $today->copy()->subDays($firstDays);
            $sent += $this->sendReminderBatch(
                $targetDate,
                'invoice_overdue_first_notice',
                'first_overdue_reminder_sent_at',
                ['unpaid', 'overdue']
            );
        }

        $secondDays = (int) Setting::getValue('second_overdue_reminder_days');
        if ($secondDays > 0) {
            $targetDate = $today->copy()->subDays($secondDays);
            $sent += $this->sendReminderBatch(
                $targetDate,
                'invoice_overdue_second_notice',
                'second_overdue_reminder_sent_at',
                ['unpaid', 'overdue']
            );
        }

        $thirdDays = (int) Setting::getValue('third_overdue_reminder_days');
        if ($thirdDays > 0) {
            $targetDate = $today->copy()->subDays($thirdDays);
            $sent += $this->sendReminderBatch(
                $targetDate,
                'invoice_overdue_third_notice',
                'third_overdue_reminder_sent_at',
                ['unpaid', 'overdue']
            );
        }

        return $sent;
    }

    private function applyTicketAutomation(Carbon $today): int
    {
        $days = (int) Setting::getValue('ticket_auto_close_days');
        if ($days <= 0) {
            return 0;
        }

        $threshold = $today->copy()->subDays($days)->endOfDay();
        $count = 0;
        $now = Carbon::now();

        SupportTicket::query()
            ->select('id')
            ->whereIn('status', ['open', 'answered', 'customer_reply'])
            ->whereNotNull('last_reply_at')
            ->where('last_reply_at', '<=', $threshold)
            ->whereNull('closed_at')
            ->orderBy('id')
            ->chunkById(200, function ($tickets) use (&$count, $now) {
                $ids = $tickets->pluck('id')->all();
                foreach ($ids as $ticketId) {
                    SendTicketAutoCloseNotification::dispatch($ticketId);
                }

                if (! empty($ids)) {
                    SupportTicket::query()
                        ->whereIn('id', $ids)
                        ->update([
                            'status' => 'closed',
                            'closed_at' => $now,
                            'auto_closed_at' => $now,
                        ]);
                    $count += count($ids);
                }
            });

        return $count;
    }

    private function sendTicketAdminReminders(Carbon $today): int
    {
        $days = (int) Setting::getValue('ticket_admin_reminder_days');
        if ($days <= 0) {
            return 0;
        }

        $threshold = $today->copy()->subDays($days)->endOfDay();
        $count = 0;
        $now = Carbon::now();

        SupportTicket::query()
            ->select('id')
            ->where('status', 'customer_reply')
            ->whereNotNull('last_reply_at')
            ->where('last_reply_at', '<=', $threshold)
            ->whereNull('admin_reminder_sent_at')
            ->orderBy('id')
            ->chunkById(200, function ($tickets) use (&$count, $now) {
                $ids = $tickets->pluck('id')->all();
                foreach ($ids as $ticketId) {
                    SendTicketAdminReminderNotification::dispatch($ticketId);
                }

                if (! empty($ids)) {
                    SupportTicket::query()
                        ->whereIn('id', $ids)
                        ->update(['admin_reminder_sent_at' => $now]);
                    $count += count($ids);
                }
            });

        return $count;
    }

    private function sendTicketFeedbackRequests(Carbon $today): int
    {
        $days = (int) Setting::getValue('ticket_feedback_days');
        if ($days <= 0) {
            return 0;
        }

        $threshold = $today->copy()->subDays($days)->endOfDay();
        $count = 0;
        $now = Carbon::now();

        SupportTicket::query()
            ->select('id')
            ->where('status', 'closed')
            ->whereNotNull('closed_at')
            ->where('closed_at', '<=', $threshold)
            ->whereNull('feedback_sent_at')
            ->orderBy('id')
            ->chunkById(200, function ($tickets) use (&$count, $now) {
                $ids = $tickets->pluck('id')->all();
                foreach ($ids as $ticketId) {
                    SendTicketFeedbackNotification::dispatch($ticketId);
                }

                if (! empty($ids)) {
                    SupportTicket::query()
                        ->whereIn('id', $ids)
                        ->update(['feedback_sent_at' => $now]);
                    $count += count($ids);
                }
            });

        return $count;
    }

    private function sendLicenseExpiryNotices(Carbon $today): int
    {
        $count = 0;
        $firstDays = (int) Setting::getValue('license_expiry_first_notice_days');
        $secondDays = (int) Setting::getValue('license_expiry_second_notice_days');
        $now = Carbon::now();

        if ($firstDays > 0) {
            $targetDate = $today->copy()->addDays($firstDays)->toDateString();
            License::query()
                ->select('id')
                ->where('status', 'active')
                ->whereDate('expires_at', $targetDate)
                ->whereNull('expiry_first_notice_sent_at')
                ->orderBy('id')
                ->chunkById(200, function ($licenses) use (&$count, $now) {
                    $ids = $licenses->pluck('id')->all();
                    foreach ($ids as $licenseId) {
                        SendLicenseExpiryNoticeNotification::dispatch($licenseId, 'license_expiry_notice');
                    }

                    if (! empty($ids)) {
                        License::query()
                            ->whereIn('id', $ids)
                            ->update(['expiry_first_notice_sent_at' => $now]);
                        $count += count($ids);
                    }
                });
        }

        if ($secondDays > 0) {
            $targetDate = $today->copy()->addDays($secondDays)->toDateString();
            License::query()
                ->select('id')
                ->where('status', 'active')
                ->whereDate('expires_at', $targetDate)
                ->whereNull('expiry_second_notice_sent_at')
                ->orderBy('id')
                ->chunkById(200, function ($licenses) use (&$count, $now) {
                    $ids = $licenses->pluck('id')->all();
                    foreach ($ids as $licenseId) {
                        SendLicenseExpiryNoticeNotification::dispatch($licenseId, 'license_expiry_notice');
                    }

                    if (! empty($ids)) {
                        License::query()
                            ->whereIn('id', $ids)
                            ->update(['expiry_second_notice_sent_at' => $now]);
                        $count += count($ids);
                    }
                });
        }

        License::query()
            ->select('id')
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $today->toDateString())
            ->whereNull('expiry_expired_notice_sent_at')
            ->orderBy('id')
            ->chunkById(200, function ($licenses) use (&$count, $now) {
                $ids = $licenses->pluck('id')->all();
                foreach ($ids as $licenseId) {
                    SendLicenseExpiryNoticeNotification::dispatch($licenseId, 'license_expired_notice');
                }

                if (! empty($ids)) {
                    License::query()
                        ->whereIn('id', $ids)
                        ->update(['expiry_expired_notice_sent_at' => $now]);
                    $count += count($ids);
                }
            });

        return $count;
    }

    private function cleanupClosedTickets(Carbon $today): int
    {
        $days = (int) Setting::getValue('ticket_cleanup_days');
        if ($days <= 0) {
            return 0;
        }

        $threshold = $today->copy()->subDays($days)->toDateTimeString();

        return SupportTicket::query()
            ->where('status', 'closed')
            ->whereNotNull('closed_at')
            ->where('closed_at', '<=', $threshold)
            ->delete();
    }

    private function sendReminderBatch(
        Carbon $targetDate,
        string $templateKey,
        string $sentColumn,
        array $statuses
    ): int {
        $count = 0;
        $now = Carbon::now();

        Invoice::query()
            ->select('id')
            ->whereIn('status', $statuses)
            ->whereDate('due_date', $targetDate->toDateString())
            ->whereNull($sentColumn)
            ->orderBy('id')
            ->chunkById(200, function ($invoices) use (&$count, $templateKey, $sentColumn, $now) {
                $ids = $invoices->pluck('id')->all();
                foreach ($ids as $invoiceId) {
                    SendInvoiceReminderNotification::dispatch($invoiceId, $templateKey);
                }

                if (! empty($ids)) {
                    Invoice::query()
                        ->whereIn('id', $ids)
                        ->update([$sentColumn => $now]);
                    $count += count($ids);
                }
            });

        return $count;
    }

    private function emptyMetrics(): array
    {
        return [
            'invoices_generated' => 0,
            'maintenance_invoices_generated' => 0,
            'maintenance_invoices_skipped' => 0,
            'invoices_overdue' => 0,
            'late_fees_added' => 0,
            'auto_cancellations' => 0,
            'suspensions' => 0,
            'terminations' => 0,
            'unsuspensions' => 0,
            'client_status_updates' => 0,
            'invoice_reminders_sent' => 0,
            'ticket_auto_closed' => 0,
            'ticket_admin_reminders' => 0,
            'ticket_feedback_requests' => 0,
            'license_expiry_notices' => 0,
            'tickets_deleted' => 0,
            'fixed_term_terminations' => 0,
        ];
    }

    private function sendCronSummary(
        string $status,
        array $metrics,
        Carbon $startedAt,
        Carbon $finishedAt,
        ?string $errorMessage
    ): void {
        SendCronSummaryEmail::dispatch(
            $status,
            $metrics,
            $startedAt->toDateTimeString(),
            $finishedAt->toDateTimeString(),
            $errorMessage
        );
    }
}
