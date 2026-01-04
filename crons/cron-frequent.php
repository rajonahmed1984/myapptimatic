<?php

/**
 * Frequent Cron Job (Every 5 Minutes)
 * 
 * Handles time-sensitive tasks:
 * - Payment processing
 * - License verification
 * - Email queue processing
 * - System monitoring
 * 
 * cPanel Schedule: Every 5 minutes (Custom)
 * Command: /usr/local/bin/ea-php82 /home/apptimatic/my.apptimatic.com/crons/cron-frequent.php
 */

use App\Models\Setting;
use Carbon\Carbon;

define('LARAVEL_START', microtime(true));

chdir(__DIR__ . '/..');

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Prevent concurrent execution
$lockFile = storage_path('framework/schedule-frequent.lock');
$lockHandle = fopen($lockFile, 'c');

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    echo "Previous cron still running\n";
    exit(0);
}

try {
    $timeZone = (string) Setting::getValue('time_zone', 'UTC');
    $now = Carbon::now($timeZone);
    
    // Update last run time
    Setting::setValue('frequent_cron_last_run', $now->toDateTimeString());
    
    echo "Frequent cron started at {$now->toDateTimeString()}\n";
    
    // Run Laravel's scheduled tasks for frequent execution
    $status = $kernel->call('schedule:run');
    echo $kernel->output();
    
    echo "Frequent cron completed successfully\n";
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    $status = 1;
} finally {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
}

exit($status ?? 0);
