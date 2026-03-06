<?php

use App\Http\Controllers\AuthFresh\PortalLoginController;
use App\Http\Controllers\Auth\RolePasswordResetController;
use App\Http\Controllers\Support\DashboardController as SupportDashboardController;
use App\Http\Controllers\Support\SupportTicketController as SupportSupportTicketController;
use App\Http\Controllers\Support\TasksController as SupportTasksController;
use App\Http\Controllers\Mail\MailInboxController;
use App\Http\Controllers\Mail\MailLoginController;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Support\Facades\Route;

Route::middleware([\App\Http\Middleware\RedirectIfAuthenticated::class . ':support', 'nocache'])
    ->prefix('support')
    ->name('support.')
    ->group(function () {
        Route::get('/login', [PortalLoginController::class, 'show'])
            ->middleware(HandleInertiaRequests::class)
            ->defaults('portal', 'support')
            ->name('login');
        Route::post('/login', [PortalLoginController::class, 'login'])
            ->defaults('portal', 'support')
            ->middleware(['throttle:login', 'login.trace'])
            ->name('login.attempt');
        Route::get('/forgot-password', [RolePasswordResetController::class, 'showSupportForgot'])
            ->middleware(HandleInertiaRequests::class)
            ->name('password.request');
        Route::post('/forgot-password', [RolePasswordResetController::class, 'sendSupportResetLink'])
            ->middleware('throttle:3,10')
            ->name('password.email');
        Route::get('/reset-password/{token}', [RolePasswordResetController::class, 'showSupportReset'])
            ->middleware(HandleInertiaRequests::class)
            ->name('password.reset');
        Route::post('/reset-password', [RolePasswordResetController::class, 'resetSupport'])->name('password.update');
    });

Route::middleware([
    'support',
    'user.activity:support',
    'nocache',
    HandleInertiaRequests::class,
])
    ->prefix('support')
    ->name('support.')
    ->group(function () {
        Route::get('/dashboard', SupportDashboardController::class)->middleware(HandleInertiaRequests::class)->name('dashboard');
        Route::get('/tasks', [SupportTasksController::class, 'index'])->name('tasks.index');
        Route::redirect('/mail', '/support/apptimatic-email');
        Route::prefix('apptimatic-email')
            ->name('apptimatic-email.')
            ->group(function () {
                Route::get('/login', [MailLoginController::class, 'showLogin'])
                    ->middleware(HandleInertiaRequests::class)
                    ->name('login');
                Route::post('/login', [MailLoginController::class, 'login'])
                    ->middleware('throttle:mail-login')
                    ->name('login.store');
                Route::post('/logout', [MailLoginController::class, 'logout'])->name('logout');

                Route::middleware(['email.auth', 'mail.session.fresh'])->group(function () {
                    Route::get('/', fn () => redirect()->route('support.apptimatic-email.inbox'));
                    Route::get('/inbox', [MailInboxController::class, 'index'])
                        ->middleware(HandleInertiaRequests::class)
                        ->name('inbox');
                    Route::get('/stream', [MailInboxController::class, 'stream'])
                        ->name('stream');
                    Route::post('/inbox/reply', [MailInboxController::class, 'reply'])
                        ->name('reply');
                    Route::get('/inbox/view={message}', [MailInboxController::class, 'show'])
                        ->middleware(HandleInertiaRequests::class)
                        ->where('message', '[A-Za-z0-9\-]+')
                        ->name('show');
                });
            });
        Route::get('/support-tickets', [SupportSupportTicketController::class, 'index'])->middleware(HandleInertiaRequests::class)->name('support-tickets.index');
        Route::get('/support-tickets/{ticket}', [SupportSupportTicketController::class, 'show'])->middleware(HandleInertiaRequests::class)->name('support-tickets.show');
        Route::post('/support-tickets/{ticket}/ai-summary', [SupportSupportTicketController::class, 'aiSummary'])->name('support-tickets.ai');
        Route::post('/support-tickets/{ticket}/reply', [SupportSupportTicketController::class, 'reply'])->name('support-tickets.reply');
        Route::patch('/support-tickets/{ticket}/status', [SupportSupportTicketController::class, 'updateStatus'])->name('support-tickets.status');
        Route::patch('/support-tickets/{ticket}', [SupportSupportTicketController::class, 'update'])->name('support-tickets.update');
        Route::delete('/support-tickets/{ticket}', [SupportSupportTicketController::class, 'destroy'])->name('support-tickets.destroy');
    });
