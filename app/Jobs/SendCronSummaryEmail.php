<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Support\Branding;
use App\Support\UrlResolver;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendCronSummaryEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $status,
        public array $metrics,
        public string $startedAt,
        public string $finishedAt,
        public ?string $errorMessage
    ) {
    }

    public function handle(): void
    {
        $to = Setting::getValue('company_email') ?: config('mail.from.address');
        if (! $to) {
            return;
        }

        $companyName = Setting::getValue('company_name', config('app.name'));
        $logoUrl = Branding::url(Setting::getValue('company_logo_path'));
        $portalUrl = UrlResolver::portalUrl();
        $portalLoginUrl = $portalUrl.'/admin';
        $dateFormat = Setting::getValue('date_format', config('app.date_format', 'd-m-Y'));
        $timeZone = Setting::getValue('time_zone', config('app.timezone'));
        $subject = $this->status === 'success'
            ? "{$companyName} Cron Job Activity"
            : "{$companyName} Cron Job Failed";

        $metricCards = [
            ['label' => 'Invoices', 'value' => $this->metrics['invoices_generated'] ?? 0, 'subtitle' => 'Generated'],
            ['label' => 'Maintenance Invoices', 'value' => $this->metrics['maintenance_invoices_generated'] ?? 0, 'subtitle' => 'Generated'],
            ['label' => 'Late Fees', 'value' => $this->metrics['late_fees_added'] ?? 0, 'subtitle' => 'Added'],
            ['label' => 'Credit Card Charges', 'value' => 0, 'subtitle' => 'Captured'],
            ['label' => 'Invoice & Overdue Reminders', 'value' => $this->metrics['invoice_reminders_sent'] ?? 0, 'subtitle' => 'Sent'],
            ['label' => 'Domain Renewal Notices', 'value' => $this->metrics['license_expiry_notices'] ?? 0, 'subtitle' => 'Sent'],
            ['label' => 'Cancellation Requests', 'value' => 0, 'subtitle' => 'Processed'],
            ['label' => 'Overdue Suspensions', 'value' => $this->metrics['suspensions'] ?? 0, 'subtitle' => 'Suspended'],
            ['label' => 'Overdue Terminations', 'value' => $this->metrics['terminations'] ?? 0, 'subtitle' => 'Terminated'],
            ['label' => 'Fixed Term Terminations', 'value' => $this->metrics['fixed_term_terminations'] ?? 0, 'subtitle' => 'Terminated'],
            ['label' => 'Auto Cancellations', 'value' => $this->metrics['auto_cancellations'] ?? 0, 'subtitle' => 'Invoices'],
            ['label' => 'Client Status Update', 'value' => $this->metrics['client_status_updates'] ?? 0, 'subtitle' => 'Completed'],
            ['label' => 'Inactive Tickets', 'value' => $this->metrics['ticket_auto_closed'] ?? 0, 'subtitle' => 'Closed'],
            ['label' => 'Process Email Campaigns', 'value' => 0, 'subtitle' => 'Emails Queued'],
            ['label' => 'Process Email Queue', 'value' => 0, 'subtitle' => 'Emails Sent'],
            ['label' => 'Email Marketer Rules', 'value' => 0, 'subtitle' => 'Emails Sent'],
            ['label' => 'SSL Sync', 'value' => 0, 'subtitle' => 'Synced'],
            ['label' => 'Domain Expiry', 'value' => 0, 'subtitle' => 'Expired'],
            ['label' => 'Data Retention Pruning', 'value' => 0, 'subtitle' => 'Deleted'],
            ['label' => 'Run Jobs Queue', 'value' => 0, 'subtitle' => 'Executed'],
        ];

        try {
            Mail::send('emails.cron-activity', [
                'subject' => $subject,
                'companyName' => $companyName,
                'logoUrl' => $logoUrl,
                'portalUrl' => $portalUrl,
                'portalLoginUrl' => $portalLoginUrl,
                'portalLoginLabel' => 'log in to the admin area',
                'status' => $this->status,
                'metrics' => $metricCards,
                'startedAt' => Carbon::parse($this->startedAt),
                'finishedAt' => Carbon::parse($this->finishedAt),
                'dateFormat' => $dateFormat,
                'timeZone' => $timeZone,
                'errorMessage' => $this->errorMessage,
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
