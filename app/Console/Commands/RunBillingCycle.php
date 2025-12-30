<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\License;
use App\Models\Setting;
use App\Models\Subscription;
use App\Services\BillingService;
use App\Services\AdminNotificationService;
use App\Services\ClientNotificationService;
use App\Models\SupportTicket;
use App\Support\Branding;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RunBillingCycle extends Command
{
    protected $signature = 'billing:run';

    protected $description = 'Generate invoices, mark overdue, apply late fees, and run automation.';

    public function __construct(
        private BillingService $billingService,
        private AdminNotificationService $adminNotifications,
        private ClientNotificationService $clientNotifications
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

        try {
            $today = Carbon::today();

            $invoiceMetrics = $this->generateInvoices($today);
            $metrics['invoices_generated'] = $invoiceMetrics['generated'];
            $metrics['fixed_term_terminations'] = $invoiceMetrics['fixed_term_terminations'];
            $metrics['invoices_overdue'] = $this->markOverdue($today);
            $metrics['late_fees_added'] = $this->applyLateFees($today);
            $metrics['auto_cancellations'] = $this->applyAutoCancellation($today);
            $metrics['suspensions'] = $this->applySuspensions($today);
            $metrics['terminations'] = $this->applyTerminations($today);
            $metrics['unsuspensions'] = $this->applyUnsuspensions();
            $metrics['invoice_reminders_sent'] = $this->sendInvoiceReminders($today);
            $metrics['ticket_auto_closed'] = $this->applyTicketAutomation($today);
            $metrics['ticket_admin_reminders'] = $this->sendTicketAdminReminders($today);
            $metrics['ticket_feedback_requests'] = $this->sendTicketFeedbackRequests($today);
            $metrics['license_expiry_notices'] = $this->sendLicenseExpiryNotices($today);
            $metrics['tickets_deleted'] = $this->cleanupClosedTickets($today);

            Setting::setValue('billing_last_run_at', Carbon::now()->toDateTimeString());
            Setting::setValue('billing_last_status', 'success');
            Setting::setValue('billing_last_metrics', json_encode($metrics));

            $this->info('Billing run completed.');
            $this->sendCronSummary('success', $metrics, $startedAt, Carbon::now(), null);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Setting::setValue('billing_last_run_at', Carbon::now()->toDateTimeString());
            Setting::setValue('billing_last_status', 'failed');
            Setting::setValue('billing_last_error', substr($e->getMessage(), 0, 500));
            Setting::setValue('billing_last_metrics', json_encode($metrics));

            $this->error('Billing run failed: ' . $e->getMessage());
            $this->sendCronSummary('failed', $metrics, $startedAt, Carbon::now(), $e->getMessage());

            return self::FAILURE;
        }
    }

    private function generateInvoices(Carbon $today): array
    {
        $count = 0;
        $fixedTermTerminations = 0;
        $subscriptions = Subscription::query()
            ->with('plan')
            ->where('status', 'active')
            ->whereDate('next_invoice_at', '<=', $today)
            ->get();

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
                $this->adminNotifications->sendInvoiceCreated($invoice);
            }
        }

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

        if ($lateFeeDays <= 0 || $lateFeeAmount <= 0) {
            return $count;
        }

        $targetDate = $today->copy()->subDays($lateFeeDays);

        $invoices = Invoice::query()
            ->whereIn('status', ['unpaid', 'overdue'])
            ->whereNull('late_fee_applied_at')
            ->whereDate('due_date', '<=', $targetDate)
            ->get();

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
                'late_fee_applied_at' => Carbon::now(),
            ]);
            $count++;
        }

        return $count;
    }

    private function applyAutoCancellation(Carbon $today): int
    {
        if (! Setting::getValue('enable_auto_cancellation')) {
            return 0;
        }

        $count = 0;
        $days = (int) Setting::getValue('auto_cancellation_days');
        $targetDate = $today->copy()->subDays($days);

        $invoices = Invoice::query()
            ->whereIn('status', ['unpaid', 'overdue'])
            ->whereDate('due_date', '<=', $targetDate)
            ->get();

        foreach ($invoices as $invoice) {
            $invoice->update([
                'status' => 'cancelled',
            ]);
            $count++;
        }

        return $count;
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

        $tickets = SupportTicket::query()
            ->whereIn('status', ['open', 'answered', 'customer_reply'])
            ->whereNotNull('last_reply_at')
            ->where('last_reply_at', '<=', $threshold)
            ->whereNull('closed_at')
            ->get();

        foreach ($tickets as $ticket) {
            $ticket->update([
                'status' => 'closed',
                'closed_at' => Carbon::now(),
                'auto_closed_at' => Carbon::now(),
            ]);
            $this->clientNotifications->sendTicketAutoClose($ticket->fresh('customer'));
            $count++;
        }

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

        $tickets = SupportTicket::query()
            ->where('status', 'customer_reply')
            ->whereNotNull('last_reply_at')
            ->where('last_reply_at', '<=', $threshold)
            ->whereNull('admin_reminder_sent_at')
            ->get();

        foreach ($tickets as $ticket) {
            $this->adminNotifications->sendTicketReminder($ticket->fresh('customer'));
            $ticket->update(['admin_reminder_sent_at' => Carbon::now()]);
            $count++;
        }

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

        $tickets = SupportTicket::query()
            ->where('status', 'closed')
            ->whereNotNull('closed_at')
            ->where('closed_at', '<=', $threshold)
            ->whereNull('feedback_sent_at')
            ->get();

        foreach ($tickets as $ticket) {
            $this->clientNotifications->sendTicketFeedback($ticket->fresh('customer'));
            $ticket->update(['feedback_sent_at' => Carbon::now()]);
            $count++;
        }

        return $count;
    }

    private function sendLicenseExpiryNotices(Carbon $today): int
    {
        $count = 0;
        $firstDays = (int) Setting::getValue('license_expiry_first_notice_days');
        $secondDays = (int) Setting::getValue('license_expiry_second_notice_days');

        if ($firstDays > 0) {
            $targetDate = $today->copy()->addDays($firstDays)->toDateString();
            $licenses = License::query()
                ->where('status', 'active')
                ->whereDate('expires_at', $targetDate)
                ->whereNull('expiry_first_notice_sent_at')
                ->get();

            foreach ($licenses as $license) {
                $this->clientNotifications->sendLicenseExpiryNotice($license, 'license_expiry_notice');
                $license->update(['expiry_first_notice_sent_at' => Carbon::now()]);
                $count++;
            }
        }

        if ($secondDays > 0) {
            $targetDate = $today->copy()->addDays($secondDays)->toDateString();
            $licenses = License::query()
                ->where('status', 'active')
                ->whereDate('expires_at', $targetDate)
                ->whereNull('expiry_second_notice_sent_at')
                ->get();

            foreach ($licenses as $license) {
                $this->clientNotifications->sendLicenseExpiryNotice($license, 'license_expiry_notice');
                $license->update(['expiry_second_notice_sent_at' => Carbon::now()]);
                $count++;
            }
        }

        $expired = License::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $today->toDateString())
            ->whereNull('expiry_expired_notice_sent_at')
            ->get();

        foreach ($expired as $license) {
            $this->clientNotifications->sendLicenseExpiryNotice($license, 'license_expired_notice');
            $license->update(['expiry_expired_notice_sent_at' => Carbon::now()]);
            $count++;
        }

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

        $invoices = Invoice::query()
            ->with('customer')
            ->whereIn('status', $statuses)
            ->whereDate('due_date', $targetDate->toDateString())
            ->whereNull($sentColumn)
            ->get();

        foreach ($invoices as $invoice) {
            $this->adminNotifications->sendInvoiceReminder($invoice, $templateKey);
            $invoice->update([$sentColumn => Carbon::now()]);
            $count++;
        }

        return $count;
    }

    private function emptyMetrics(): array
    {
        return [
            'invoices_generated' => 0,
            'invoices_overdue' => 0,
            'late_fees_added' => 0,
            'auto_cancellations' => 0,
            'suspensions' => 0,
            'terminations' => 0,
            'unsuspensions' => 0,
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
        $to = Setting::getValue('company_email') ?: config('mail.from.address');
        if (! $to) {
            return;
        }

        $companyName = Setting::getValue('company_name', config('app.name'));
        $logoUrl = Branding::url(Setting::getValue('company_logo_path'));
        $portalUrl = rtrim(config('app.url'), '/');
        $portalLoginUrl = $portalUrl . '/admin';
        $dateFormat = Setting::getValue('date_format', config('app.date_format', 'd-m-Y'));
        $timeZone = Setting::getValue('time_zone', config('app.timezone'));
        $subject = $status === 'success'
            ? "{$companyName} Cron Job Activity"
            : "{$companyName} Cron Job Failed";

        $metricCards = [
            ['label' => 'Invoices', 'value' => $metrics['invoices_generated'], 'subtitle' => 'Generated'],
            ['label' => 'Invoices', 'value' => $metrics['invoices_overdue'], 'subtitle' => 'Marked Overdue'],
            ['label' => 'Late Fees', 'value' => $metrics['late_fees_added'], 'subtitle' => 'Added'],
            ['label' => 'Suspensions', 'value' => $metrics['suspensions'], 'subtitle' => 'Applied'],
            ['label' => 'Terminations', 'value' => $metrics['terminations'], 'subtitle' => 'Applied'],
            ['label' => 'Unsuspensions', 'value' => $metrics['unsuspensions'], 'subtitle' => 'Processed'],
            ['label' => 'Auto Cancel', 'value' => $metrics['auto_cancellations'], 'subtitle' => 'Invoices'],
        ];

        try {
            Mail::send('emails.cron-activity', [
                'subject' => $subject,
                'companyName' => $companyName,
                'logoUrl' => $logoUrl,
                'portalUrl' => $portalUrl,
                'portalLoginUrl' => $portalLoginUrl,
                'portalLoginLabel' => 'log in to the admin area',
                'status' => $status,
                'metrics' => $metricCards,
                'startedAt' => $startedAt,
                'finishedAt' => $finishedAt,
                'dateFormat' => $dateFormat,
                'timeZone' => $timeZone,
                'errorMessage' => $errorMessage,
            ], function ($message) use ($to, $subject, $companyName) {
                $message->to($to)->subject($subject);

                $fromEmail = Setting::getValue('company_email');
                if ($fromEmail) {
                    $message->from($fromEmail, $companyName);
                }
            });
        } catch (\Throwable $e) {
            Log::warning('Failed to send cron activity email.', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
