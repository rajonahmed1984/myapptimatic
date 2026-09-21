<?php

use App\Http\Controllers\Api\LicenseVerificationController;
use App\Http\Controllers\Api\ChatbotLeadController;
use Illuminate\Support\Facades\Route;

Route::post('/licenses/verify', [LicenseVerificationController::class, 'verify'])
    ->middleware(['throttle:license-verify', 'verify.api.signature'])
    ->name('api.licenses.verify');

// A MyBuilding installation reports building changes and reads back the
// licence state it should enforce. Always signed - see the middleware.
Route::post('/licenses/sync', [\App\Http\Controllers\Api\MyBuildingSyncController::class, 'sync'])
    ->middleware(['throttle:license-verify', \App\Http\Middleware\VerifyMyBuildingSignature::class])
    ->name('api.licenses.sync');

Route::post('/chatbot/leads', [ChatbotLeadController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('api.chatbot.leads.store');

Route::get('/bkash-token', [\App\Http\Controllers\Api\BkashBridgeController::class, 'getToken'])
    ->middleware('bkash.bridge')
    ->name('api.bkash.token');

