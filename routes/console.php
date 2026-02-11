<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\RunPayroll;
use App\Models\Setting;
use App\Support\CronActivityLogger;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

$automationTime = '00:00';
$automationTimezone = config('app.timezone', 'UTC');
try {
    $automationTime = (string) Setting::getValue('automation_time_of_day', '00:00');
    $automationTimezone = Setting::getValue('time_zone', config('app.timezone', 'UTC')) ?: 'UTC';
} catch (\Throwable) {
    $automationTime = '00:00';
    $automationTimezone = config('app.timezone', 'UTC');
}

// Daily billing cycle (12:00 AM via cron.php)
$billing = Schedule::command('billing:run')->daily();
CronActivityLogger::track($billing, 'billing:run');

// Frequent tasks (every 5 minutes via cron-frequent.php)
$queue = Schedule::command('queue:work --stop-when-empty')->everyFiveMinutes()->withoutOverlapping();
CronActivityLogger::track($queue, 'queue:work --stop-when-empty');
// Schedule::command('horizon:snapshot')->everyFiveMinutes(); // Horizon not required for database queue driver
$payroll = Schedule::command('payroll:run')->monthlyOn(1, '03:00'); // Monthly payroll draft
CronActivityLogger::track($payroll, 'payroll:run');

// Activity tracking: close stale sessions hourly
$closeUserSessions = Schedule::command('user-sessions:close-stale')->hourly();
CronActivityLogger::track($closeUserSessions, 'user-sessions:close-stale');

// Cron health monitor: alert if billing cron missed/failed.
$cronMonitor = Schedule::command('cron:monitor')->hourly();
CronActivityLogger::track($cronMonitor, 'cron:monitor');

// Close stale employee sessions so online indicators stay accurate.
$closeEmployeeSessions = Schedule::command('employee-sessions:close-stale')->hourly();
CronActivityLogger::track($closeEmployeeSessions, 'employee-sessions:close-stale');

// Generate daily work summaries for remote part-time/full-time employees.
$workSummaries = Schedule::command('employee-work-summaries:generate')->dailyAt('00:10');
CronActivityLogger::track($workSummaries, 'employee-work-summaries:generate');

// Generate recurring expense occurrences.
$expensesRecurring = Schedule::command('expenses:generate-recurring')->dailyAt('00:15');
CronActivityLogger::track($expensesRecurring, 'expenses:generate-recurring');

// AI chat summaries (project daily, task weekly).
$projectChatAi = Schedule::command('chat:ai-summary --type=project --days=7 --limit=200 --email --email-limit=30')->dailyAt('00:00');
CronActivityLogger::track($projectChatAi, 'chat:ai-summary --type=project --days=7 --limit=200 --email --email-limit=30');

$taskChatAi = Schedule::command('chat:ai-summary --type=task --days=14 --limit=200 --email --email-limit=30')->weeklyOn(5, '01:00');
CronActivityLogger::track($taskChatAi, 'chat:ai-summary --type=task --days=14 --limit=200 --email --email-limit=30');

// Daily license sync summary and automation reports.
$licenseSyncLog = Schedule::command('licenses:sync-log')
    ->dailyAt($automationTime)
    ->timezone($automationTimezone)
    ->withoutOverlapping();
CronActivityLogger::track($licenseSyncLog, 'licenses:sync-log');

$dailyReports = Schedule::command('reports:daily')
    ->dailyAt($automationTime)
    ->timezone($automationTimezone)
    ->withoutOverlapping();
CronActivityLogger::track($dailyReports, 'reports:daily');

// Payment processing checks
$pendingPayments = Schedule::call(function () {
    // Process pending payment attempts
    \App\Models\PaymentAttempt::where('status', 'pending')
        ->where('created_at', '<=', now()->subMinutes(5))
        ->chunk(50, function ($attempts) {
            foreach ($attempts as $attempt) {
                // Your payment verification logic
            }
        });
})->everyFiveMinutes()->name('process-pending-payments');
CronActivityLogger::track($pendingPayments, 'process-pending-payments');

// License verification checks
$verifyLicenses = Schedule::call(function () {
    // Verify active licenses
    \App\Models\License::where('status', 'active')
        ->whereNotNull('last_verified_at')
        ->where('last_verified_at', '<=', now()->subHours(1))
        ->chunk(100, function ($licenses) {
            foreach ($licenses as $license) {
                \App\Jobs\EvaluateLicenseRiskJob::dispatch($license);
            }
        });
})->everyFiveMinutes()->name('verify-licenses');
CronActivityLogger::track($verifyLicenses, 'verify-licenses');
