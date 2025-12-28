<?php

use App\Http\Controllers\Api\LicenseVerificationController;
use Illuminate\Support\Facades\Route;

Route::post('/licenses/verify', [LicenseVerificationController::class, 'verify'])
    ->name('api.licenses.verify');
