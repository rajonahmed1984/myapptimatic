<?php

use App\Http\Controllers\Api\LicenseVerificationController;
use App\Http\Controllers\Api\ChatbotLeadController;
use Illuminate\Support\Facades\Route;

Route::post('/licenses/verify', [LicenseVerificationController::class, 'verify'])
    ->middleware(['throttle:license-verify', 'verify.api.signature'])
    ->name('api.licenses.verify');

Route::post('/chatbot/leads', [ChatbotLeadController::class, 'store'])
    ->name('api.chatbot.leads.store');

