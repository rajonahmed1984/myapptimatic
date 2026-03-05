<?php

use App\Http\Controllers\CronController;
use Illuminate\Support\Facades\Route;

Route::match(['GET', 'POST'], '/cron/billing', [CronController::class, 'billing'])
    // Operational endpoint (signed + token protected), intentionally kept server-rendered (Blade).
    ->middleware(['restrict.cron', 'throttle:cron-endpoint'])
    ->name('cron.billing');
