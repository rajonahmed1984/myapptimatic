<?php

use App\Http\Controllers\Admin\AccountingController as AdminAccountingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\AffiliateCommissionController;
use App\Http\Controllers\Admin\AffiliateController as AdminAffiliateController;
use App\Http\Controllers\Admin\AffiliatePayoutController;
use App\Http\Controllers\Admin\AutomationStatusController;
use App\Http\Controllers\Admin\AiBusinessStatusController;
use App\Http\Controllers\Admin\ChatController as AdminChatController;
use App\Http\Controllers\Admin\SystemCacheController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerProjectUserController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\InvoiceController as AdminInvoiceController;
use App\Http\Controllers\Admin\UserActivitySummaryController;
use App\Http\Controllers\Admin\PaymentProofController as AdminPaymentProofController;
use App\Http\Controllers\Admin\LicenseController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\PaymentGatewayController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\CommissionPayoutController;
use App\Http\Controllers\Admin\CommissionExportController;
use App\Http\Controllers\Admin\UserDocumentController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\TasksController as AdminTasksController;
use App\Http\Controllers\Admin\SupportTicketController as AdminSupportTicketController;
use App\Http\Controllers\Admin\SystemLogController;
use App\Http\Controllers\Admin\MilestoneController;
use App\Http\Controllers\Admin\ExpenseController as AdminExpenseController;
use App\Http\Controllers\Admin\ExpenseCategoryController as AdminExpenseCategoryController;
use App\Http\Controllers\Admin\RecurringExpenseController as AdminRecurringExpenseController;
use App\Http\Controllers\Admin\ExpenseDashboardController as AdminExpenseDashboardController;
use App\Http\Controllers\Admin\CarrotHostIncomeController as AdminCarrotHostIncomeController;
use App\Http\Controllers\Admin\ExpenseInvoiceController as AdminExpenseInvoiceController;
use App\Http\Controllers\Admin\FinanceReportController as AdminFinanceReportController;
use App\Http\Controllers\Admin\FinanceTaxController as AdminFinanceTaxController;
use App\Http\Controllers\Admin\Finance\PaymentMethodController as AdminPaymentMethodController;
use App\Http\Controllers\Admin\Hr\DashboardController as HrDashboardController;
use App\Http\Controllers\Admin\Hr\AttendanceController as HrAttendanceController;
use App\Http\Controllers\Admin\Hr\PaidHolidayController as HrPaidHolidayController;
use App\Http\Controllers\Admin\Hr\PayrollController as HrPayrollController;
use App\Http\Controllers\Admin\IncomeCategoryController as AdminIncomeCategoryController;
use App\Http\Controllers\Admin\IncomeController as AdminIncomeController;
use App\Http\Controllers\AuthFresh\PortalLoginController;
use App\Http\Controllers\AuthFresh\LogoutController;
use App\Http\Controllers\Auth\RolePasswordResetController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Employee\DashboardController as EmployeeDashboardController;
use App\Http\Controllers\Employee\AttendanceController as EmployeeAttendanceController;
use App\Http\Controllers\Employee\ChatController as EmployeeChatController;
use App\Http\Controllers\Employee\TasksController as EmployeeTasksController;
use App\Http\Controllers\Employee\ProfileController as EmployeeProfileController;
use App\Http\Controllers\Employee\TimesheetController as EmployeeTimesheetController;
use App\Http\Controllers\Employee\LeaveRequestController as EmployeeLeaveRequestController;
use App\Http\Controllers\Employee\PayrollController as EmployeePayrollController;
use App\Http\Controllers\Employee\WorkSessionController as EmployeeWorkSessionController;
use App\Http\Controllers\Client\AffiliateController as ClientAffiliateController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\ChatController as ClientChatController;
use App\Http\Controllers\Client\TasksController as ClientTasksController;
use App\Http\Controllers\Client\DomainController as ClientDomainController;
use App\Http\Controllers\Client\InvoiceController as ClientInvoiceController;
use App\Http\Controllers\Client\ManualPaymentController;
use App\Http\Controllers\Client\LicenseController as ClientLicenseController;
use App\Http\Controllers\Client\OrderController as ClientOrderController;
use App\Http\Controllers\Client\ProfileController as ClientProfileController;
use App\Http\Controllers\Client\ServiceController as ClientServiceController;
use App\Http\Controllers\Client\SupportTicketController as ClientSupportTicketController;
use App\Http\Controllers\SalesRep\DashboardController as SalesRepDashboardController;
use App\Http\Controllers\SalesRep\EarningController as SalesRepEarningController;
use App\Http\Controllers\SalesRep\ChatController as SalesRepChatController;
use App\Http\Controllers\SalesRep\PayoutController as SalesRepPayoutController;
use App\Http\Controllers\SalesRep\ProfileController as SalesRepProfileController;
use App\Http\Controllers\SalesRep\TasksController as SalesRepTasksController;
use App\Http\Controllers\Support\DashboardController as SupportDashboardController;
use App\Http\Controllers\Support\SupportTicketController as SupportSupportTicketController;
use App\Http\Controllers\Support\TasksController as SupportTasksController;
use App\Http\Controllers\ProjectClient\AuthController as ProjectClientAuthController;
use App\Models\PaymentAttempt;
use App\Http\Controllers\BrandingAssetController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\SupportTicketAttachmentController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PaymentCallbackController;
use App\Http\Controllers\PublicProductController;
use App\Http\Controllers\PublicMediaController;
use App\Http\Controllers\ProjectChatController;
use App\Http\Controllers\ProjectTaskChatController;
use App\Http\Controllers\ProjectTaskViewController;
use App\Http\Middleware\HandleInertiaRequests;
use App\Support\UiFeature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;
use App\Models\Project;
use Inertia\Inertia;

Route::redirect('/', '/login')->name('products.public.home');

Route::get('/__ui/react-sandbox', function () {
    abort_unless(UiFeature::enabled(UiFeature::REACT_SANDBOX), 404);

    return Inertia::render('Sandbox', [
        'generated_at' => now()->toIso8601String(),
    ]);
})->middleware(HandleInertiaRequests::class)->name('ui.react-sandbox');

Route::redirect('/admin', '/admin/login');
Route::get('/employee', fn () => redirect()->route('employee.login'))->name('employee.home');
Route::get('/sales', fn () => redirect()->route('sales.login'))->name('sales.home');
Route::get('/support', fn () => redirect()->route('support.login'))->name('support.home');
Route::get('media/avatars/{path}', [PublicMediaController::class, 'avatar'])
    ->where('path', '.*')
    ->name('media.avatars');

Route::get('storage/avatars/{category}/{entity}/{filename}', function (string $category, string $entity, string $filename) {
    $allowed = ['customers', 'users', 'sales-reps'];
    if (! in_array($category, $allowed, true)) {
        abort(404);
    }

    $path = "avatars/{$category}/{$entity}/{$filename}";
    if (! Storage::disk('public')->exists($path)) {
        abort(404);
    }

    return Storage::disk('public')->response($path);
})->where('category', 'customers|employees|users|sales-reps')
    ->where('entity', '\d+')
    ->where('filename', '.*');

Route::get('storage/employees/photos/{path}', function (string $path) {
    if (str_contains($path, '..')) {
        abort(404);
    }

    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (! in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
        abort(404);
    }

    $filePath = 'employees/photos/' . ltrim($path, '/');
    if (! Storage::disk('public')->exists($filePath)) {
        abort(404);
    }

    return Storage::disk('public')->response($filePath);
})->where('path', '.*');

// PaymentAttempt route binding supports UUID (preferred) and falls back to numeric ID for legacy links.
Route::bind('attempt', function ($value) {
    return PaymentAttempt::query()
        ->where('uuid', $value)
        ->orWhere('id', $value)
        ->firstOrFail();
});

Route::get('/branding/{path}', [BrandingAssetController::class, 'show'])
    ->where('path', '.*')
    ->name('branding.asset');

require __DIR__ . '/cron.php';

Route::get('/support-ticket-replies/{reply}/attachment', [SupportTicketAttachmentController::class, 'show'])
    ->whereNumber('reply')
    ->middleware('auth:web,support')
    ->name('support-ticket-replies.attachment');


Route::match(['GET', 'POST'], '/payments/sslcommerz/{attempt}/success', [PaymentCallbackController::class, 'sslcommerzSuccess'])
    ->middleware('throttle:payment-callbacks')
    ->name('payments.sslcommerz.success');
Route::match(['GET', 'POST'], '/payments/sslcommerz/{attempt}/fail', [PaymentCallbackController::class, 'sslcommerzFail'])
    ->middleware('throttle:payment-callbacks')
    ->name('payments.sslcommerz.fail');
Route::match(['GET', 'POST'], '/payments/sslcommerz/{attempt}/cancel', [PaymentCallbackController::class, 'sslcommerzCancel'])
    ->middleware('throttle:payment-callbacks')
    ->name('payments.sslcommerz.cancel');
Route::get('/payments/paypal/{attempt}/return', [PaymentCallbackController::class, 'paypalReturn'])
    ->middleware('throttle:payment-callbacks')
    ->name('payments.paypal.return');
Route::get('/payments/paypal/{attempt}/cancel', [PaymentCallbackController::class, 'paypalCancel'])
    ->middleware('throttle:payment-callbacks')
    ->name('payments.paypal.cancel');
Route::match(['GET', 'POST'], '/payments/bkash/{attempt}/callback', [PaymentCallbackController::class, 'bkashCallback'])
    ->middleware('throttle:payment-callbacks')
    ->name('payments.bkash.callback');

Route::middleware([\App\Http\Middleware\RedirectIfAuthenticated::class . ':web', 'nocache'])->group(function () {
    Route::get('/login', [PortalLoginController::class, 'show'])
        ->middleware(HandleInertiaRequests::class)
        ->defaults('portal', 'web')
        ->name('login');
    Route::post('/login', [PortalLoginController::class, 'login'])
        ->defaults('portal', 'web')
        ->middleware(['throttle:login', 'login.trace'])
        ->name('login.attempt');
    Route::get('/project-login', [ProjectClientAuthController::class, 'showLogin'])
        ->middleware(HandleInertiaRequests::class)
        ->name('project-client.login');
    Route::post('/project-login', [ProjectClientAuthController::class, 'login'])
        ->middleware(['throttle:login', 'login.trace'])
        ->name('project-client.login.attempt');
    Route::get('/register', [AuthController::class, 'showRegister'])
        ->middleware(HandleInertiaRequests::class)
        ->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
    Route::get('/admin/login', [PortalLoginController::class, 'show'])
        ->middleware(HandleInertiaRequests::class)
        ->defaults('portal', 'admin')
        ->name('admin.login');
    Route::post('/admin/login', [PortalLoginController::class, 'login'])
        ->defaults('portal', 'admin')
        ->middleware(['throttle:login', 'login.trace'])
        ->name('admin.login.attempt');
    Route::get('/admin/forgot-password', [PasswordResetController::class, 'requestAdmin'])
        ->middleware(HandleInertiaRequests::class)
        ->name('admin.password.request');
    Route::post('/admin/forgot-password', [PasswordResetController::class, 'emailAdmin'])->name('admin.password.email');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])
        ->middleware(HandleInertiaRequests::class)
        ->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])
        ->middleware(HandleInertiaRequests::class)
        ->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

require __DIR__ . '/portals/employee.php';

require __DIR__ . '/portals/sales.php';

require __DIR__ . '/portals/support.php';

if (app()->environment(['local', 'testing'])) {
    Route::get('/v1/license-risk', function () {
        return response()->json([
            'ok' => true,
            'message' => 'Local AI license-risk mock is reachable. Use POST for risk checks.',
        ]);
    })->name('mock.ai.license-risk.health')
        ->middleware('throttle:60,1');

    Route::post('/v1/license-risk', function (Request $request) {
        $rawBody = $request->getContent() ?? '';
        $timestamp = $request->header('X-Timestamp');
        $signature = $request->header('X-Signature');
        $authHeader = (string) $request->header('Authorization', '');

        $configuredToken = (string) config('ai.token', '');
        if ($configuredToken !== '') {
            $expectedAuth = 'Bearer ' . $configuredToken;
            if (! hash_equals($expectedAuth, $authHeader)) {
                return response()->json([
                    'risk_score' => 0.0,
                    'decision' => 'allow',
                    'reason' => 'mock_unauthorized_token',
                    'details' => [],
                ], 401);
            }
        }

        $configuredSecret = (string) config('ai.hmac_secret', '');
        if ($configuredSecret !== '') {
            if (! is_string($timestamp) || ! is_string($signature)) {
                return response()->json([
                    'risk_score' => 0.0,
                    'decision' => 'allow',
                    'reason' => 'mock_missing_signature',
                    'details' => [],
                ], 401);
            }

            $expectedSignature = hash_hmac('sha256', $timestamp . '.' . $rawBody, $configuredSecret);
            if (! hash_equals($expectedSignature, $signature)) {
                return response()->json([
                    'risk_score' => 0.0,
                    'decision' => 'allow',
                    'reason' => 'mock_invalid_signature',
                    'details' => [],
                ], 401);
            }
        }

        $payload = $request->json()->all();
        $incomingDecision = strtolower((string) ($payload['decision'] ?? 'allow'));
        $incomingReason = strtolower((string) ($payload['reason'] ?? ''));
        $domain = strtolower((string) ($payload['domain'] ?? ''));

        $riskScore = 0.05;
        $decision = 'allow';
        $reason = 'mock_allow';

        if ($incomingDecision === 'block' || $incomingReason === 'domain_not_allowed') {
            $riskScore = 0.95;
            $decision = 'block';
            $reason = 'mock_domain_risk';
        } elseif ($incomingDecision === 'warn' || $incomingReason === 'invoice_due') {
            $riskScore = 0.65;
            $decision = 'warn';
            $reason = 'mock_billing_risk';
        } elseif ($domain !== '' && str_contains($domain, 'suspicious')) {
            $riskScore = 0.8;
            $decision = 'warn';
            $reason = 'mock_suspicious_domain';
        }

        return response()->json([
            'risk_score' => $riskScore,
            'decision' => $decision,
            'reason' => $reason,
            'details' => [
                'mock' => true,
                'received_request_id' => $payload['request_id'] ?? null,
            ],
        ]);
    })->name('mock.ai.license-risk')
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
        ->middleware('throttle:60,1');
}

Route::post('/logout', [LogoutController::class, 'logout'])
    ->name('logout')
    ->middleware('auth:web,employee,sales,support');
Route::post('/impersonate/stop', [AuthController::class, 'stopImpersonate'])
    ->name('impersonate.stop')
    ->middleware('auth');


require __DIR__ . '/portals/admin.php';

Route::middleware([
    'auth',
    'client',
    'client.block',
    'client.notice',
    'user.activity:web',
    'project.client',
    'nocache',
    HandleInertiaRequests::class,
])
    ->prefix('client')
    ->name('client.')
    ->group(function () {
        Route::get('/projects/{project}/tasks/{task}', [ProjectTaskViewController::class, 'show'])->name('projects.tasks.show');
    });

Route::middleware([
    'auth',
    'client',
    'client.block',
    'client.notice',
    'user.activity:web',
    'project.client',
    'nocache',
    HandleInertiaRequests::class,
])
    ->prefix('client')
    ->name('client.')
    ->group(function () {
        Route::get('/dashboard', [ClientDashboardController::class, 'index'])
            ->middleware(HandleInertiaRequests::class)
            ->name('dashboard');
        Route::get('/tasks', [ClientTasksController::class, 'index'])
            ->middleware(HandleInertiaRequests::class)
            ->name('tasks.index');
        Route::get('/chats', [ClientChatController::class, 'index'])
            ->middleware(HandleInertiaRequests::class)
            ->name('chats.index');
        Route::redirect('/chat', '/client/chats');
        Route::post('/system/cache/clear', SystemCacheController::class)->name('system.cache.clear');
        Route::get('/orders', [ClientOrderController::class, 'index'])
            ->middleware('project.financial')
            ->middleware(HandleInertiaRequests::class)
            ->name('orders.index');
        Route::get('/orders/review', [ClientOrderController::class, 'review'])
            ->middleware('project.financial')
            ->middleware(HandleInertiaRequests::class)
            ->name('orders.review');
        Route::post('/orders', [ClientOrderController::class, 'store'])->middleware('project.financial')->name('orders.store');
        Route::get('/services', [ClientServiceController::class, 'index'])
            ->middleware('project.financial')
            ->middleware(HandleInertiaRequests::class)
            ->name('services.index');
        Route::get('/services/{subscription}', [ClientServiceController::class, 'show'])
            ->middleware('project.financial')
            ->middleware(HandleInertiaRequests::class)
            ->name('services.show');
        Route::get('/invoices', [ClientInvoiceController::class, 'index'])->middleware('project.financial')->middleware(HandleInertiaRequests::class)->name('invoices.index');
        Route::get('/invoices/paid', [ClientInvoiceController::class, 'paid'])->middleware('project.financial')->middleware(HandleInertiaRequests::class)->name('invoices.paid');
        Route::get('/invoices/unpaid', [ClientInvoiceController::class, 'unpaid'])->middleware('project.financial')->middleware(HandleInertiaRequests::class)->name('invoices.unpaid');
        Route::get('/invoices/overdue', [ClientInvoiceController::class, 'overdue'])->middleware('project.financial')->middleware(HandleInertiaRequests::class)->name('invoices.overdue');
        Route::get('/invoices/cancelled', [ClientInvoiceController::class, 'cancelled'])->middleware('project.financial')->middleware(HandleInertiaRequests::class)->name('invoices.cancelled');
        Route::get('/invoices/refunded', [ClientInvoiceController::class, 'refunded'])->middleware('project.financial')->middleware(HandleInertiaRequests::class)->name('invoices.refunded');
        Route::get('/invoices/{invoice}', [ClientInvoiceController::class, 'show'])->middleware('project.financial')->middleware(HandleInertiaRequests::class)->name('invoices.show');
        Route::get('/profile', [ClientProfileController::class, 'edit'])
            ->middleware(HandleInertiaRequests::class)
            ->name('profile.edit');
        Route::put('/profile', [ClientProfileController::class, 'update'])->name('profile.update');
        Route::get('/invoices/{invoice}/pay', [ClientInvoiceController::class, 'pay'])->middleware('project.financial')->middleware(HandleInertiaRequests::class)->name('invoices.pay');
        Route::post('/invoices/{invoice}/checkout', [ClientInvoiceController::class, 'checkout'])->middleware('project.financial')->name('invoices.checkout');
        Route::get('/invoices/{invoice}/manual/{attempt}', [ManualPaymentController::class, 'create'])->middleware('project.financial')->middleware(HandleInertiaRequests::class)->name('invoices.manual');
        Route::post('/invoices/{invoice}/manual/{attempt}', [ManualPaymentController::class, 'store'])->middleware('project.financial')->name('invoices.manual.store');
        Route::get('/invoices/{invoice}/download', [ClientInvoiceController::class, 'download'])->middleware('project.financial')->name('invoices.download');
        Route::get('/domains', [ClientDomainController::class, 'index'])
            ->middleware('project.financial')
            ->middleware(HandleInertiaRequests::class)
            ->name('domains.index');
        Route::get('/domains/{domain}', [ClientDomainController::class, 'show'])
            ->middleware('project.financial')
            ->middleware(HandleInertiaRequests::class)
            ->name('domains.show');
        Route::get('/licenses', [ClientLicenseController::class, 'index'])
            ->middleware('project.financial')
            ->middleware(HandleInertiaRequests::class)
            ->name('licenses.index');
        Route::get('/projects', [\App\Http\Controllers\Client\ProjectController::class, 'index'])
            ->middleware(HandleInertiaRequests::class)
            ->name('projects.index');
        Route::get('/projects/{project}', [\App\Http\Controllers\Client\ProjectController::class, 'show'])
            ->middleware(HandleInertiaRequests::class)
            ->name('projects.show');
        Route::post('/projects/{project}/tasks', [\App\Http\Controllers\Client\ProjectTaskController::class, 'store'])->name('projects.tasks.store');
        Route::patch('/projects/{project}/tasks/{task}', [\App\Http\Controllers\Client\ProjectTaskController::class, 'update'])->name('projects.tasks.update');
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
        Route::get('/support-tickets', [ClientSupportTicketController::class, 'index'])
            ->middleware(HandleInertiaRequests::class)
            ->name('support-tickets.index');
        Route::get('/support-tickets/create', [ClientSupportTicketController::class, 'create'])
            ->middleware(HandleInertiaRequests::class)
            ->name('support-tickets.create');
        Route::post('/support-tickets', [ClientSupportTicketController::class, 'store'])->name('support-tickets.store');
        Route::get('/support-tickets/{ticket}', [ClientSupportTicketController::class, 'show'])
            ->middleware(HandleInertiaRequests::class)
            ->name('support-tickets.show');
        Route::post('/support-tickets/{ticket}/reply', [ClientSupportTicketController::class, 'reply'])->name('support-tickets.reply');
        Route::patch('/support-tickets/{ticket}/status', [ClientSupportTicketController::class, 'updateStatus'])->name('support-tickets.status');
        
        // Affiliate routes
        Route::get('/affiliates', [ClientAffiliateController::class, 'index'])
            ->middleware('project.financial')
            ->middleware(HandleInertiaRequests::class)
            ->name('affiliates.index');
        Route::get('/affiliates/apply', [ClientAffiliateController::class, 'apply'])
            ->middleware('project.financial')
            ->middleware(HandleInertiaRequests::class)
            ->name('affiliates.apply');
        Route::post('/affiliates/apply', [ClientAffiliateController::class, 'storeApplication'])->middleware('project.financial')->name('affiliates.apply.store');
        Route::get('/affiliates/referrals', [ClientAffiliateController::class, 'referrals'])
            ->middleware('project.financial')
            ->middleware(HandleInertiaRequests::class)
            ->name('affiliates.referrals');
        Route::get('/affiliates/commissions', [ClientAffiliateController::class, 'commissions'])
            ->middleware('project.financial')
            ->middleware(HandleInertiaRequests::class)
            ->name('affiliates.commissions');
        Route::get('/affiliates/payouts', [ClientAffiliateController::class, 'payouts'])
            ->middleware('project.financial')
            ->middleware(HandleInertiaRequests::class)
            ->name('affiliates.payouts');
        Route::get('/affiliates/settings', [ClientAffiliateController::class, 'settings'])
            ->middleware('project.financial')
            ->middleware(HandleInertiaRequests::class)
            ->name('affiliates.settings');
        Route::put('/affiliates/settings', [ClientAffiliateController::class, 'updateSettings'])->middleware('project.financial')->name('affiliates.settings.update');
    });



Route::middleware('signed:relative')->group(function () {
    Route::get('/chat/project-messages/{message}/inline', [ProjectChatController::class, 'inlineAttachment'])
        ->name('chat.project-messages.inline');
    Route::get('/chat/task-messages/{message}/inline', [ProjectTaskChatController::class, 'inlineAttachment'])
        ->name('chat.task-messages.inline');
});

Route::get('/products', [PublicProductController::class, 'index'])
    ->middleware([HandleInertiaRequests::class])
    ->name('products.public.index');

Route::get('/{product:slug}/plans/{plan:slug}', [PublicProductController::class, 'showPlan'])
    ->middleware([HandleInertiaRequests::class])
    ->name('products.public.plan');

Route::get('/{product:slug}', [PublicProductController::class, 'show'])
    ->middleware([HandleInertiaRequests::class])
    ->name('products.public.show');
