<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\UrlResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AutomationStatusService
{
    public function getStatusPayload(): array
    {
        $cronToken = (string) Setting::getValue('cron_token');
        $baseUrl = UrlResolver::portalUrl();
        $cronUrl = $cronToken !== '' ? "{$baseUrl}/cron/billing?token={$cronToken}" : null;

        $lastRunAt = Setting::getValue('billing_last_run_at');
        $lastStartedAt = Setting::getValue('billing_last_started_at');
        $lastStatus = Setting::getValue('billing_last_status');
        $lastError = Setting::getValue('billing_last_error');

        $lastRun = $lastRunAt ? Carbon::parse($lastRunAt) : null;
        $lastStarted = $lastStartedAt ? Carbon::parse($lastStartedAt) : null;

        $nextDailyRun = $this->calculateNextDailyRun($lastStarted);
        $status = $this->statusBadge($lastStatus);
        $metrics = $this->metrics();
        $timeZone = Setting::getValue('time_zone', config('app.timezone', 'UTC')) ?: 'UTC';
        $portalTime = Carbon::now($timeZone);

        $cronStatusLabel = $lastStatus ? ucfirst($lastStatus) : 'Never';
        $cronStatusClasses = match ($lastStatus) {
            'success' => 'bg-emerald-100 text-emerald-700',
            'failed' => 'bg-rose-100 text-rose-700',
            'running' => 'bg-amber-100 text-amber-700',
            default => 'bg-slate-100 text-slate-600',
        };

        $cronSetup = $cronToken !== '';
        $cronInvocationWindowHours = 24;
        $dailyCronWindowHours = 24;
        $cronInvoked = $lastRun && $lastRun->diffInHours(Carbon::now()) <= $cronInvocationWindowHours;
        $dailyCronRun = $lastRun && $lastRun->diffInHours(Carbon::now()) <= $dailyCronWindowHours;
        $dailyCronCompleting = $lastStatus === 'success';

        $aiEnabled = (bool) config('ai.enabled');
        $aiRiskEnabled = (bool) config('ai.license_risk_enabled');
        $aiQueuePending = $this->queueCount('jobs', 'ai');
        $aiQueueFailed = $this->queueCount('failed_jobs', 'ai');
        $aiStatus = $this->aiStatusBadge($aiEnabled, $aiRiskEnabled, $aiQueueFailed);

        $dailyActions = [
            [
                'label' => 'Invoices',
                'enabled' => true,
                'disabled_label' => null,
                'stats' => [
                    ['value' => $metrics['invoices_generated'], 'label' => 'Generated'],
                ],
            ],
            [
                'label' => 'Late Fees',
                'enabled' => (int) Setting::getValue('late_fee_days') > 0 && (float) Setting::getValue('late_fee_amount') > 0,
                'disabled_label' => 'Disabled',
                'stats' => [
                    ['value' => $metrics['late_fees_added'], 'label' => 'Added'],
                ],
            ],
            [
                'label' => 'Invoice & Overdue Reminders',
                'enabled' => (bool) Setting::getValue('payment_reminder_emails'),
                'disabled_label' => 'Disabled',
                'stats' => [
                    ['value' => $metrics['invoice_reminders_sent'], 'label' => 'Sent'],
                ],
            ],
            [
                'label' => 'Overdue Suspensions',
                'enabled' => (bool) Setting::getValue('enable_suspension'),
                'disabled_label' => 'Disabled',
                'stats' => [
                    ['value' => $metrics['suspensions'], 'label' => 'Suspended'],
                    ['value' => 0, 'label' => 'Failed'],
                ],
            ],
            [
                'label' => 'Overdue Terminations',
                'enabled' => (bool) Setting::getValue('enable_termination'),
                'disabled_label' => 'Disabled',
                'stats' => [
                    ['value' => $metrics['terminations'], 'label' => 'Terminated'],
                    ['value' => 0, 'label' => 'Failed'],
                ],
            ],
            [
                'label' => 'Fixed Term Terminations',
                'enabled' => true,
                'disabled_label' => null,
                'stats' => [
                    ['value' => $metrics['fixed_term_terminations'], 'label' => 'Terminated'],
                    ['value' => 0, 'label' => 'Failed'],
                ],
            ],
            [
                'label' => 'Overdue Invoice Cancellations',
                'enabled' => (bool) Setting::getValue('enable_auto_cancellation'),
                'disabled_label' => 'Disabled',
                'stats' => [
                    ['value' => $metrics['auto_cancellations'], 'label' => 'Cancelled'],
                ],
            ],
            [
                'label' => 'Inactive Tickets',
                'enabled' => (int) Setting::getValue('ticket_auto_close_days') > 0,
                'disabled_label' => 'Disabled',
                'stats' => [
                    ['value' => $metrics['ticket_auto_closed'], 'label' => 'Closed'],
                ],
            ],
            [
                'label' => 'Client Status Update',
                'enabled' => true,
                'disabled_label' => null,
                'stats' => [
                    ['value' => $metrics['client_status_updates'], 'label' => 'Updated'],
                ],
            ],
        ];

        return [
            'statusLabel' => $status['label'],
            'statusClasses' => $status['classes'],
            'lastStatus' => $lastStatus,
            'lastError' => $lastError,
            'lastInvocationText' => $lastStarted ? $lastStarted->diffForHumans() : 'Never',
            'lastInvocationAt' => $lastStarted ? $lastStarted->format('M d, Y H:i:s') : 'Not yet invoked',
            'lastCompletionText' => $lastRun ? $lastRun->diffForHumans() : 'Never',
            'lastCompletionAt' => $lastRun ? $lastRun->format('M d, Y H:i:s') : 'Not yet completed',
            'nextDailyRunText' => $nextDailyRun ? $nextDailyRun->diffForHumans() : 'Not scheduled',
            'nextDailyRunAt' => $nextDailyRun ? $nextDailyRun->format('M d, Y H:i:s') : 'No historical run',
            'dailyActions' => $dailyActions,
            'cronSetup' => $cronSetup,
            'cronInvoked' => $cronInvoked,
            'dailyCronRun' => $dailyCronRun,
            'dailyCronCompleting' => $dailyCronCompleting,
            'cronStatusLabel' => $cronStatusLabel,
            'cronStatusClasses' => $cronStatusClasses,
            'cronUrl' => $cronUrl,
            'cronInvocationWindowHours' => $cronInvocationWindowHours,
            'dailyCronWindowHours' => $dailyCronWindowHours,
            'portalTimeZone' => $timeZone,
            'portalTimeLabel' => $portalTime->format('g:i:s A'),
            'automationConfig' => $this->automationConfig(),
            'aiHealth' => [
                'enabled' => $aiEnabled,
                'risk_enabled' => $aiRiskEnabled,
                'queue_pending' => $aiQueuePending,
                'queue_failed' => $aiQueueFailed,
                'status_label' => $aiStatus['label'],
                'status_classes' => $aiStatus['classes'],
                'queue_connection' => config('queue.default'),
            ],
        ];
    }

    private function calculateNextDailyRun(?Carbon $lastStarted): ?Carbon
    {
        if (! $lastStarted) {
            return null;
        }

        $nextDailyRun = $lastStarted->copy();
        while ($nextDailyRun->isPast()) {
            $nextDailyRun->addDay();
        }

        return $nextDailyRun;
    }

    private function metrics(): array
    {
        $defaults = [
            'invoices_generated' => 0,
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

        $raw = Setting::getValue('billing_last_metrics');
        if (! is_string($raw) || $raw === '') {
            return $defaults;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return $defaults;
        }

        return array_merge($defaults, $decoded);
    }

    private function automationConfig(): array
    {
        $timeOfDay = (string) Setting::getValue('automation_time_of_day', '00:00');

        return [
            'time_of_day' => $this->formatAutomationTime($timeOfDay),
            'enable_suspension' => (bool) Setting::getValue('enable_suspension'),
            'suspend_days' => (int) Setting::getValue('suspend_days'),
            'send_suspension_email' => (bool) Setting::getValue('send_suspension_email'),
            'enable_unsuspension' => (bool) Setting::getValue('enable_unsuspension'),
            'send_unsuspension_email' => (bool) Setting::getValue('send_unsuspension_email'),
            'enable_termination' => (bool) Setting::getValue('enable_termination'),
            'termination_days' => (int) Setting::getValue('termination_days'),
            'invoice_generation_days' => (int) Setting::getValue('invoice_generation_days'),
            'payment_reminder_emails' => (bool) Setting::getValue('payment_reminder_emails'),
            'invoice_unpaid_reminder_days' => (int) Setting::getValue('invoice_unpaid_reminder_days'),
            'first_overdue_reminder_days' => (int) Setting::getValue('first_overdue_reminder_days'),
            'second_overdue_reminder_days' => (int) Setting::getValue('second_overdue_reminder_days'),
            'third_overdue_reminder_days' => (int) Setting::getValue('third_overdue_reminder_days'),
            'late_fee_days' => (int) Setting::getValue('late_fee_days'),
            'overage_billing_mode' => Setting::getValue('overage_billing_mode'),
            'change_invoice_status_on_reversal' => (bool) Setting::getValue('change_invoice_status_on_reversal'),
            'change_due_dates_on_reversal' => (bool) Setting::getValue('change_due_dates_on_reversal'),
            'enable_auto_cancellation' => (bool) Setting::getValue('enable_auto_cancellation'),
            'auto_cancellation_days' => (int) Setting::getValue('auto_cancellation_days'),
            'ticket_auto_close_days' => (int) Setting::getValue('ticket_auto_close_days'),
            'ticket_admin_reminder_days' => (int) Setting::getValue('ticket_admin_reminder_days'),
            'ticket_feedback_days' => (int) Setting::getValue('ticket_feedback_days'),
            'ticket_cleanup_days' => (int) Setting::getValue('ticket_cleanup_days'),
        ];
    }

    private function formatAutomationTime(string $value): string
    {
        try {
            return Carbon::createFromFormat('H:i', $value)->format('g:ia');
        } catch (\Throwable) {
            return '12:00am';
        }
    }

    private function statusBadge(?string $status): array
    {
        return match ($status) {
            'success' => ['label' => 'Ok', 'classes' => 'bg-emerald-100 text-emerald-700'],
            'running' => ['label' => 'Running', 'classes' => 'bg-blue-100 text-blue-700'],
            'failed' => ['label' => 'Failed', 'classes' => 'bg-rose-100 text-rose-700'],
            default => ['label' => 'Pending', 'classes' => 'bg-slate-100 text-slate-600'],
        };
    }

    private function aiStatusBadge(bool $enabled, bool $riskEnabled, int $failed): array
    {
        if (! $enabled || ! $riskEnabled) {
            return ['label' => 'Disabled', 'classes' => 'bg-slate-100 text-slate-600'];
        }

        if ($failed > 0) {
            return ['label' => 'Attention', 'classes' => 'bg-amber-100 text-amber-700'];
        }

        return ['label' => 'Healthy', 'classes' => 'bg-emerald-100 text-emerald-700'];
    }

    private function queueCount(string $table, string $queue): int
    {
        try {
            return (int) DB::table($table)->where('queue', $queue)->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
