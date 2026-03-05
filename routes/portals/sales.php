<?php

use App\Http\Controllers\AuthFresh\PortalLoginController;
use App\Http\Controllers\Auth\RolePasswordResetController;
use App\Http\Controllers\SalesRep\DashboardController as SalesRepDashboardController;
use App\Http\Controllers\SalesRep\EarningController as SalesRepEarningController;
use App\Http\Controllers\SalesRep\ChatController as SalesRepChatController;
use App\Http\Controllers\SalesRep\PayoutController as SalesRepPayoutController;
use App\Http\Controllers\SalesRep\ProfileController as SalesRepProfileController;
use App\Http\Controllers\SalesRep\TasksController as SalesRepTasksController;
use App\Http\Controllers\Admin\SystemCacheController;
use App\Http\Controllers\ProjectChatController;
use App\Http\Controllers\ProjectTaskChatController;
use App\Http\Controllers\ProjectTaskViewController;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Support\Facades\Route;

Route::middleware(['guest:sales', 'nocache'])
    ->prefix('sales')
    ->name('sales.')
    ->group(function () {
        Route::get('/login', [PortalLoginController::class, 'show'])
            ->middleware(HandleInertiaRequests::class)
            ->defaults('portal', 'sales')
            ->name('login');
        Route::post('/login', [PortalLoginController::class, 'login'])
            ->defaults('portal', 'sales')
            ->middleware(['throttle:login', 'login.trace'])
            ->name('login.attempt');
        Route::get('/forgot-password', [RolePasswordResetController::class, 'showSalesForgot'])
            ->middleware(HandleInertiaRequests::class)
            ->name('password.request');
        Route::post('/forgot-password', [RolePasswordResetController::class, 'sendSalesResetLink'])
            ->middleware('throttle:3,10')
            ->name('password.email');
        Route::get('/reset-password/{token}', [RolePasswordResetController::class, 'showSalesReset'])
            ->middleware(HandleInertiaRequests::class)
            ->name('password.reset');
        Route::post('/reset-password', [RolePasswordResetController::class, 'resetSales'])->name('password.update');
    });

Route::middleware([
    'salesrep',
    'user.activity:sales',
    'nocache',
    HandleInertiaRequests::class,
])
    ->prefix('sales')
    ->name('rep.')
    ->group(function () {
        Route::get('/projects/{project}/tasks/{task}', [ProjectTaskViewController::class, 'show'])->name('projects.tasks.show');
    });

Route::middleware([
    'salesrep',
    'user.activity:sales',
    'nocache',
    HandleInertiaRequests::class,
])
    ->prefix('sales')
    ->name('rep.')
    ->group(function () {
        Route::get('/dashboard', SalesRepDashboardController::class)->middleware(HandleInertiaRequests::class)->name('dashboard');
        Route::get('/tasks', [SalesRepTasksController::class, 'index'])->middleware(HandleInertiaRequests::class)->name('tasks.index');
        Route::get('/chats', [SalesRepChatController::class, 'index'])->middleware(HandleInertiaRequests::class)->name('chats.index');
        Route::redirect('/chat', '/sales/chats');
        Route::get('/profile', [SalesRepProfileController::class, 'edit'])->middleware(HandleInertiaRequests::class)->name('profile.edit');
        Route::put('/profile', [SalesRepProfileController::class, 'update'])->name('profile.update');
        Route::post('/system/cache/clear', SystemCacheController::class)
            ->name('system.cache.clear');
        Route::get('/earnings', [SalesRepEarningController::class, 'index'])->middleware(HandleInertiaRequests::class)->name('earnings.index');
        Route::get('/payouts', [SalesRepPayoutController::class, 'index'])->middleware(HandleInertiaRequests::class)->name('payouts.index');
        Route::get('/projects', [\App\Http\Controllers\SalesRep\ProjectController::class, 'index'])->middleware(HandleInertiaRequests::class)->name('projects.index');
        Route::get('/projects/{project}', [\App\Http\Controllers\SalesRep\ProjectController::class, 'show'])->middleware(HandleInertiaRequests::class)->name('projects.show');
        Route::post('/projects/{project}/tasks', [\App\Http\Controllers\SalesRep\ProjectTaskController::class, 'store'])->name('projects.tasks.store');
        Route::patch('/projects/{project}/tasks/{task}', [\App\Http\Controllers\SalesRep\ProjectTaskController::class, 'update'])->name('projects.tasks.update');
        Route::delete('/projects/{project}/tasks/{task}', [\App\Http\Controllers\SalesRep\ProjectTaskController::class, 'destroy'])->name('projects.tasks.destroy');
        Route::post('/projects/{project}/tasks/{task}/subtasks', [\App\Http\Controllers\ProjectTaskSubtaskController::class, 'store'])
            ->name('projects.tasks.subtasks.store');
        Route::patch('/projects/{project}/tasks/{task}/subtasks/{subtask}', [\App\Http\Controllers\ProjectTaskSubtaskController::class, 'update'])
            ->name('projects.tasks.subtasks.update');
        Route::delete('/projects/{project}/tasks/{task}/subtasks/{subtask}', [\App\Http\Controllers\ProjectTaskSubtaskController::class, 'destroy'])
            ->name('projects.tasks.subtasks.destroy');
        Route::post('/projects/{project}/tasks/{task}/subtasks/{subtask}/comments', [\App\Http\Controllers\ProjectTaskSubtaskCommentController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('projects.tasks.subtasks.comments.store');
        Route::get('/projects/{project}/tasks/{task}/subtasks/{subtask}/attachment', [\App\Http\Controllers\ProjectTaskSubtaskController::class, 'attachment'])
            ->name('projects.tasks.subtasks.attachment');
        Route::get('/projects/{project}/chat', [ProjectChatController::class, 'show'])->name('projects.chat');
        Route::get('/projects/{project}/chat/participants', [ProjectChatController::class, 'participants'])
            ->name('projects.chat.participants');
        Route::get('/projects/{project}/chat/messages', [ProjectChatController::class, 'messages'])->name('projects.chat.messages');
        Route::get('/projects/{project}/chat/stream', [ProjectChatController::class, 'stream'])
            ->name('projects.chat.stream');
        Route::post('/projects/{project}/chat/ai-summary', [ProjectChatController::class, 'aiSummary'])->name('projects.chat.ai');
        Route::post('/projects/{project}/chat/messages', [ProjectChatController::class, 'storeMessage'])
            ->middleware('throttle:10,1')
            ->name('projects.chat.messages.store');
        Route::patch('/projects/{project}/chat/messages/{message}', [ProjectChatController::class, 'updateMessage'])
            ->middleware('throttle:20,1')
            ->name('projects.chat.messages.update');
        Route::delete('/projects/{project}/chat/messages/{message}', [ProjectChatController::class, 'destroyMessage'])
            ->middleware('throttle:20,1')
            ->name('projects.chat.messages.destroy');
        Route::post('/projects/{project}/chat/messages/{message}/pin', [ProjectChatController::class, 'togglePin'])
            ->middleware('throttle:20,1')
            ->name('projects.chat.messages.pin');
        Route::post('/projects/{project}/chat/messages/{message}/react', [ProjectChatController::class, 'toggleReaction'])
            ->middleware('throttle:40,1')
            ->name('projects.chat.messages.react');
        Route::patch('/projects/{project}/chat/read', [ProjectChatController::class, 'markRead'])
            ->name('projects.chat.read');
        Route::post('/projects/{project}/chat/presence', [ProjectChatController::class, 'presence'])
            ->name('projects.chat.presence');
        Route::post('/projects/{project}/chat', [ProjectChatController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('projects.chat.store');
        Route::get('/projects/{project}/chat/messages/{message}/attachment', [ProjectChatController::class, 'attachment'])
            ->name('projects.chat.messages.attachment');
        Route::get('/projects/{project}/tasks/{task}/chat', [ProjectTaskChatController::class, 'show'])->name('projects.tasks.chat');
        Route::get('/projects/{project}/tasks/{task}/chat/messages', [ProjectTaskChatController::class, 'messages'])->name('projects.tasks.chat.messages');
        Route::post('/projects/{project}/tasks/{task}/chat/ai-summary', [ProjectTaskChatController::class, 'aiSummary'])->name('projects.tasks.chat.ai');
        Route::post('/projects/{project}/tasks/{task}/chat/messages', [ProjectTaskChatController::class, 'storeMessage'])
            ->middleware('throttle:10,1')
            ->name('projects.tasks.chat.messages.store');
        Route::patch('/projects/{project}/tasks/{task}/chat/messages/{message}', [ProjectTaskChatController::class, 'updateMessage'])
            ->middleware('throttle:20,1')
            ->name('projects.tasks.chat.messages.update');
        Route::delete('/projects/{project}/tasks/{task}/chat/messages/{message}', [ProjectTaskChatController::class, 'destroyMessage'])
            ->middleware('throttle:20,1')
            ->name('projects.tasks.chat.messages.destroy');
        Route::post('/projects/{project}/tasks/{task}/chat/messages/{message}/pin', [ProjectTaskChatController::class, 'togglePin'])
            ->middleware('throttle:20,1')
            ->name('projects.tasks.chat.messages.pin');
        Route::post('/projects/{project}/tasks/{task}/chat/messages/{message}/react', [ProjectTaskChatController::class, 'toggleReaction'])
            ->middleware('throttle:40,1')
            ->name('projects.tasks.chat.messages.react');
        Route::patch('/projects/{project}/tasks/{task}/chat/read', [ProjectTaskChatController::class, 'markRead'])
            ->name('projects.tasks.chat.read');
        Route::post('/projects/{project}/tasks/{task}/chat', [ProjectTaskChatController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('projects.tasks.chat.store');
        Route::get('/projects/{project}/tasks/{task}/messages/{message}/attachment', [ProjectTaskChatController::class, 'attachment'])->name('projects.tasks.messages.attachment');
    });
