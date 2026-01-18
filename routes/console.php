<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\RunPayroll;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Daily billing cycle (12:00 AM via cron.php)
Schedule::command('billing:run')->daily();

// Frequent tasks (every 5 minutes via cron-frequent.php)
Schedule::command('queue:work --stop-when-empty')->everyFiveMinutes()->withoutOverlapping();
// Schedule::command('horizon:snapshot')->everyFiveMinutes(); // Horizon not required for database queue driver
Schedule::command('payroll:run')->monthlyOn(1, '03:00'); // Monthly payroll draft

// Activity tracking: close stale sessions hourly
Schedule::command('user-sessions:close-stale')->hourly();

// Cron health monitor: alert if billing cron missed/failed.
Schedule::command('cron:monitor')->hourly();

// Close stale employee sessions so online indicators stay accurate.
Schedule::command('employee-sessions:close-stale')->hourly();

// Payment processing checks
Schedule::call(function () {
    // Process pending payment attempts
    \App\Models\PaymentAttempt::where('status', 'pending')
        ->where('created_at', '<=', now()->subMinutes(5))
        ->chunk(50, function ($attempts) {
            foreach ($attempts as $attempt) {
                // Your payment verification logic
            }
        });
})->everyFiveMinutes()->name('process-pending-payments');

// License verification checks
Schedule::call(function () {
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
