<?php

use App\Http\Controllers\Api\LicenseVerificationController;
use Illuminate\Support\Facades\Route;

Route::post('/licenses/verify', [LicenseVerificationController::class, 'verify'])
    ->middleware(['throttle:license-verify', 'verify.api.signature'])
    ->name('api.licenses.verify');
