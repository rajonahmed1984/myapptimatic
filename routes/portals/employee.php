<?php

use App\Http\Controllers\AuthFresh\PortalLoginController;
use App\Http\Controllers\Auth\RolePasswordResetController;
use App\Http\Controllers\Employee\DashboardController as EmployeeDashboardController;
use App\Http\Controllers\Employee\AttendanceController as EmployeeAttendanceController;
use App\Http\Controllers\Employee\ChatController as EmployeeChatController;
use App\Http\Controllers\Employee\TasksController as EmployeeTasksController;
use App\Http\Controllers\Employee\ProfileController as EmployeeProfileController;
use App\Http\Controllers\Employee\TimesheetController as EmployeeTimesheetController;
use App\Http\Controllers\Employee\LeaveRequestController as EmployeeLeaveRequestController;
use App\Http\Controllers\Employee\PayrollController as EmployeePayrollController;
use App\Http\Controllers\Employee\WorkSessionController as EmployeeWorkSessionController;
use App\Http\Controllers\ProjectChatController;
use App\Http\Controllers\ProjectTaskChatController;
use App\Http\Controllers\ProjectTaskViewController;
use App\Http\Controllers\Mail\MailInboxController;
use App\Http\Controllers\Mail\MailLoginController;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Support\Facades\Route;

Route::middleware([\App\Http\Middleware\RedirectIfAuthenticated::class . ':employee', 'nocache'])
    ->prefix('employee')
    ->name('employee.')
    ->group(function () {
        Route::get('/login', [PortalLoginController::class, 'show'])
            ->middleware(HandleInertiaRequests::class)
            ->defaults('portal', 'employee')
            ->name('login');
        Route::post('/login', [PortalLoginController::class, 'login'])
            ->defaults('portal', 'employee')
            ->middleware(['throttle:login', 'login.trace'])
            ->name('login.attempt');
        Route::get('/forgot-password', [RolePasswordResetController::class, 'showEmployeeForgot'])
            ->middleware(HandleInertiaRequests::class)
            ->name('password.request');
        Route::post('/forgot-password', [RolePasswordResetController::class, 'sendEmployeeResetLink'])
            ->middleware('throttle:3,10')
            ->name('password.email');
        Route::get('/reset-password/{token}', [RolePasswordResetController::class, 'showEmployeeReset'])
            ->middleware(HandleInertiaRequests::class)
            ->name('password.reset');
        Route::post('/reset-password', [RolePasswordResetController::class, 'resetEmployee'])->name('password.update');
    });

Route::middleware([
    'auth:employee',
    'employee',
    'employee.activity',
    'user.activity:employee',
    'nocache',
    HandleInertiaRequests::class,
])
    ->prefix('employee')
    ->name('employee.')
    ->group(function () {
        Route::get('/projects/{project}/tasks/{task}', [ProjectTaskViewController::class, 'show'])->name('projects.tasks.show');
    });

Route::middleware([
    'auth:employee',
    'employee',
    'employee.activity',
    'user.activity:employee',
    'nocache',
    HandleInertiaRequests::class,
])
    ->prefix('employee')
    ->name('employee.')
    ->group(function () {
        Route::get('/dashboard', EmployeeDashboardController::class)->middleware(HandleInertiaRequests::class)->name('dashboard');
        Route::get('/tasks', [EmployeeTasksController::class, 'index'])->middleware(HandleInertiaRequests::class)->name('tasks.index');
        Route::get('/chats', [EmployeeChatController::class, 'index'])->middleware(HandleInertiaRequests::class)->name('chats.index');
        Route::redirect('/chat', '/employee/chats');
        Route::redirect('/mail', '/employee/apptimatic-email');
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
                    Route::get('/', [MailInboxController::class, 'index'])
                        ->middleware(HandleInertiaRequests::class)
                        ->name('inbox');
                    Route::get('/stream', [MailInboxController::class, 'stream'])
                        ->name('stream');
                    Route::get('/messages/{message}', [MailInboxController::class, 'show'])
                        ->middleware(HandleInertiaRequests::class)
                        ->where('message', '[A-Za-z0-9\-]+')
                        ->name('show');
                });
            });
        Route::post('/work-sessions/start', [EmployeeWorkSessionController::class, 'start'])->name('work-sessions.start');
        Route::post('/work-sessions/ping', [EmployeeWorkSessionController::class, 'ping'])->name('work-sessions.ping');
        Route::post('/work-sessions/stop', [EmployeeWorkSessionController::class, 'stop'])->name('work-sessions.stop');
        Route::get('/work-summaries/today', [EmployeeWorkSessionController::class, 'today'])->name('work-summaries.today');
        Route::get('/profile', [EmployeeProfileController::class, 'edit'])->middleware(HandleInertiaRequests::class)->name('profile.edit');
        Route::put('/profile', [EmployeeProfileController::class, 'update'])->name('profile.update');
        Route::get('/work-logs', [EmployeeTimesheetController::class, 'index'])->middleware(HandleInertiaRequests::class)->name('timesheets.index');
        Route::redirect('/timesheets', '/employee/work-logs');
        Route::get('/leave-requests', [EmployeeLeaveRequestController::class, 'index'])->middleware(HandleInertiaRequests::class)->name('leave-requests.index');
        Route::post('/leave-requests', [EmployeeLeaveRequestController::class, 'store'])->name('leave-requests.store');
        Route::get('/attendance', [EmployeeAttendanceController::class, 'index'])->middleware(HandleInertiaRequests::class)->name('attendance.index');
        Route::get('/payroll', [EmployeePayrollController::class, 'index'])->middleware(HandleInertiaRequests::class)->name('payroll.index');
        Route::get('/projects', [\App\Http\Controllers\Employee\ProjectController::class, 'index'])->middleware(HandleInertiaRequests::class)->name('projects.index');
        Route::get('/projects/{project}', [\App\Http\Controllers\Employee\ProjectController::class, 'show'])->middleware(HandleInertiaRequests::class)->name('projects.show');
        Route::post('/projects/{project}/tasks', [\App\Http\Controllers\Employee\ProjectTaskController::class, 'store'])->name('projects.tasks.store');
        Route::patch('/projects/{project}/tasks/{task}', [\App\Http\Controllers\Employee\ProjectTaskController::class, 'update'])->name('projects.tasks.update');
        Route::patch('/projects/{project}/tasks/{task}/start', [\App\Http\Controllers\Employee\ProjectTaskController::class, 'start'])->name('projects.tasks.start');
        Route::delete('/projects/{project}/tasks/{task}', [\App\Http\Controllers\Employee\ProjectTaskController::class, 'destroy'])->name('projects.tasks.destroy');
        Route::post('/projects/{project}/tasks/{task}/subtasks', [\App\Http\Controllers\ProjectTaskSubtaskController::class, 'store'])->name('projects.tasks.subtasks.store');
        Route::patch('/projects/{project}/tasks/{task}/subtasks/{subtask}', [\App\Http\Controllers\ProjectTaskSubtaskController::class, 'update'])->name('projects.tasks.subtasks.update');
        Route::delete('/projects/{project}/tasks/{task}/subtasks/{subtask}', [\App\Http\Controllers\ProjectTaskSubtaskController::class, 'destroy'])->name('projects.tasks.subtasks.destroy');
        Route::post('/projects/{project}/tasks/{task}/subtasks/{subtask}/comments', [\App\Http\Controllers\ProjectTaskSubtaskCommentController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('projects.tasks.subtasks.comments.store');
        Route::get('/projects/{project}/tasks/{task}/subtasks/{subtask}/attachment', [\App\Http\Controllers\ProjectTaskSubtaskController::class, 'attachment'])->name('projects.tasks.subtasks.attachment');
        Route::post('/projects/{project}/tasks/{task}/subtasks', [\App\Http\Controllers\ProjectTaskSubtaskController::class, 'store'])
            ->name('projects.tasks.subtasks.store');
        Route::patch('/projects/{project}/tasks/{task}/subtasks/{subtask}', [\App\Http\Controllers\ProjectTaskSubtaskController::class, 'update'])
            ->name('projects.tasks.subtasks.update');
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
