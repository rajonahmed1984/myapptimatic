<?php

/**
 * Daily Billing Cron Job (12:00 AM)
 * 
 * Handles daily batch operations:
 * - Invoice generation
 * - Overdue processing
 * - Late fees
 * - Suspensions/Terminations
 * - Reminders and notifications
 * 
 * cPanel Schedule: 0 0 * * *
 * Command: /usr/local/bin/ea-php82 /home/apptimatic/my.apptimatic.com/crons/cron.php
 */

use App\Models\Setting;
use Carbon\Carbon;
use DateTimeZone;

define('LARAVEL_START', microtime(true));

chdir(__DIR__ . '/..');

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$shouldRun = true;
$timeZone = (string) Setting::getValue('time_zone', 'UTC');
if ($timeZone === '' || ! in_array($timeZone, DateTimeZone::listIdentifiers(), true)) {
    $timeZone = 'UTC';
}
$today = Carbon::now($timeZone);

try {
    $lastStatus = (string) Setting::getValue('billing_last_status');
    $lastRunAt = Setting::getValue('billing_last_run_at');

    if ($lastStatus === 'success' && $lastRunAt) {
        $lastRun = Carbon::parse($lastRunAt, $timeZone);
        if ($lastRun->isSameDay($today)) {
            $shouldRun = false;
        }
    }
} catch (\Throwable $e) {
    $shouldRun = true;
}

if ($shouldRun) {
    $status = $kernel->call('billing:run');
    echo $kernel->output();
} else {
    $status = 0;
    echo "billing:run already executed for {$today->toDateString()}\n";
}

exit($status);
