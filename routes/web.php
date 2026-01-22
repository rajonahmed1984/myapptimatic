<?php

use App\Http\Controllers\Admin\AccountingController as AdminAccountingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\AffiliateCommissionController;
use App\Http\Controllers\Admin\AffiliateController as AdminAffiliateController;
use App\Http\Controllers\Admin\AffiliatePayoutController;
use App\Http\Controllers\Admin\AutomationStatusController;
use App\Http\Controllers\Admin\ChatController as AdminChatController;
use App\Http\Controllers\Admin\SystemCacheController;
use App\Http\Controllers\Admin\ClientRequestController as AdminClientRequestController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerProjectUserController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\InvoiceController as AdminInvoiceController;
use App\Http\Controllers\Admin\EmployeeSummaryController;
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
use App\Http\Controllers\Admin\ExpenseInvoiceController as AdminExpenseInvoiceController;
use App\Http\Controllers\Admin\FinanceReportController as AdminFinanceReportController;
use App\Http\Controllers\Admin\FinanceTaxController as AdminFinanceTaxController;
use App\Http\Controllers\Admin\Hr\DashboardController as HrDashboardController;
use App\Http\Controllers\Admin\Hr\PayrollController as HrPayrollController;
use App\Http\Controllers\Admin\IncomeCategoryController as AdminIncomeCategoryController;
use App\Http\Controllers\Admin\IncomeController as AdminIncomeController;
use App\Http\Controllers\Auth\RoleLoginController;
use App\Http\Controllers\Auth\RolePasswordResetController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Employee\AuthController as EmployeeAuthController;
use App\Http\Controllers\Employee\DashboardController as EmployeeDashboardController;
use App\Http\Controllers\Employee\ChatController as EmployeeChatController;
use App\Http\Controllers\Employee\TasksController as EmployeeTasksController;
use App\Http\Controllers\Employee\ProfileController as EmployeeProfileController;
use App\Http\Controllers\Employee\TimesheetController as EmployeeTimesheetController;
use App\Http\Controllers\Employee\LeaveRequestController as EmployeeLeaveRequestController;
use App\Http\Controllers\Employee\PayrollController as EmployeePayrollController;
use App\Http\Controllers\Employee\WorkSessionController as EmployeeWorkSessionController;
use App\Http\Controllers\Client\AffiliateController as ClientAffiliateController;
use App\Http\Controllers\Client\ClientRequestController;
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
use App\Http\Controllers\ProjectChatController;
use App\Http\Controllers\ProjectTaskChatController;
use App\Http\Controllers\ProjectTaskActivityController;
use App\Http\Controllers\ProjectTaskViewController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;
use App\Models\Project;

Route::get('/', [PublicProductController::class, 'index'])
    ->name('products.public.home');

Route::redirect('/admin', '/admin/login');
Route::get('/employee', fn () => redirect()->route('employee.login'))->name('employee.home');
Route::get('/sales', fn () => redirect()->route('sales.login'))->name('sales.home');
Route::get('/support', fn () => redirect()->route('support.login'))->name('support.home');

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
})->where('category', 'customers|users|sales-reps')
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

Route::match(['GET', 'POST'], '/cron/billing', [CronController::class, 'billing'])
    ->middleware(['restrict.cron', 'throttle:cron-endpoint'])
    ->name('cron.billing');

Route::get('/support-ticket-replies/{reply}/attachment', [SupportTicketAttachmentController::class, 'show'])
    ->whereNumber('reply')
    ->middleware('auth:web,support')
    ->name('support-ticket-replies.attachment');

Route::middleware('auth:web,support')
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('user-documents/{type}/{id}/{doc}', [UserDocumentController::class, 'show'])
            ->whereIn('type', ['employee', 'customer', 'sales-rep', 'user'])
            ->whereNumber('id')
            ->whereIn('doc', ['nid', 'cv'])
            ->name('user-documents.show');
    });

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

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    Route::get('/project-login', [ProjectClientAuthController::class, 'showLogin'])->name('project-client.login');
    Route::post('/project-login', [ProjectClientAuthController::class, 'login'])->name('project-client.login.attempt');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
    Route::get('/admin/login', [AuthController::class, 'showAdminLogin'])->name('admin.login');
    Route::post('/admin/login', [AuthController::class, 'adminLogin'])->name('admin.login.attempt');
    Route::get('/admin/forgot-password', [PasswordResetController::class, 'requestAdmin'])->name('admin.password.request');
    Route::post('/admin/forgot-password', [PasswordResetController::class, 'emailAdmin'])->name('admin.password.email');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::middleware('guest:employee')
    ->prefix('employee')
    ->name('employee.')
    ->group(function () {
        Route::get('/login', [EmployeeAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [EmployeeAuthController::class, 'login'])->name('login.attempt');
        Route::get('/forgot-password', [RolePasswordResetController::class, 'showEmployeeForgot'])->name('password.request');
        Route::post('/forgot-password', [RolePasswordResetController::class, 'sendEmployeeResetLink'])
            ->middleware('throttle:3,10')
            ->name('password.email');
        Route::get('/reset-password/{token}', [RolePasswordResetController::class, 'showEmployeeReset'])->name('password.reset');
        Route::post('/reset-password', [RolePasswordResetController::class, 'resetEmployee'])->name('password.update');
    });

Route::middleware('guest:sales')
    ->prefix('sales')
    ->name('sales.')
    ->group(function () {
        Route::get('/login', [RoleLoginController::class, 'showSalesLogin'])->name('login');
        Route::post('/login', [RoleLoginController::class, 'loginSales'])->name('login.attempt');
        Route::get('/forgot-password', [RolePasswordResetController::class, 'showSalesForgot'])->name('password.request');
        Route::post('/forgot-password', [RolePasswordResetController::class, 'sendSalesResetLink'])
            ->middleware('throttle:3,10')
            ->name('password.email');
        Route::get('/reset-password/{token}', [RolePasswordResetController::class, 'showSalesReset'])->name('password.reset');
        Route::post('/reset-password', [RolePasswordResetController::class, 'resetSales'])->name('password.update');
    });

Route::middleware('guest:support')
    ->prefix('support')
    ->name('support.')
    ->group(function () {
        Route::get('/login', [RoleLoginController::class, 'showSupportLogin'])->name('login');
        Route::post('/login', [RoleLoginController::class, 'loginSupport'])->name('login.attempt');
        Route::get('/forgot-password', [RolePasswordResetController::class, 'showSupportForgot'])->name('password.request');
        Route::post('/forgot-password', [RolePasswordResetController::class, 'sendSupportResetLink'])
            ->middleware('throttle:3,10')
            ->name('password.email');
        Route::get('/reset-password/{token}', [RolePasswordResetController::class, 'showSupportReset'])->name('password.reset');
        Route::post('/reset-password', [RolePasswordResetController::class, 'resetSupport'])->name('password.update');
    });

Route::post('/logout', [AuthController::class, 'logout'])
    ->name('logout')
    ->middleware('auth:web');
Route::post('/admin/logout', [AuthController::class, 'logoutAdmin'])
    ->name('admin.logout')
    ->middleware('auth:web');
Route::post('/impersonate/stop', [AuthController::class, 'stopImpersonate'])
    ->name('impersonate.stop')
    ->middleware('auth');

Route::middleware(['auth:employee', 'employee', 'employee.activity', 'user.activity:employee', 'nocache'])
    ->prefix('employee')
    ->name('employee.')
    ->group(function () {
        Route::post('/logout', [EmployeeAuthController::class, 'logout'])->name('logout');
        Route::get('/dashboard', EmployeeDashboardController::class)->name('dashboard');
        Route::get('/tasks', [EmployeeTasksController::class, 'index'])->name('tasks.index');
        Route::get('/chats', [EmployeeChatController::class, 'index'])->name('chats.index');
        Route::redirect('/chat', '/employee/chats');
        Route::post('/work-sessions/start', [EmployeeWorkSessionController::class, 'start'])->name('work-sessions.start');
        Route::post('/work-sessions/ping', [EmployeeWorkSessionController::class, 'ping'])->name('work-sessions.ping');
        Route::post('/work-sessions/stop', [EmployeeWorkSessionController::class, 'stop'])->name('work-sessions.stop');
        Route::get('/work-summaries/today', [EmployeeWorkSessionController::class, 'today'])->name('work-summaries.today');
        Route::get('/profile', [EmployeeProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [EmployeeProfileController::class, 'update'])->name('profile.update');
        Route::get('/timesheets', [EmployeeTimesheetController::class, 'index'])->name('timesheets.index');
        Route::post('/timesheets', [EmployeeTimesheetController::class, 'store'])->name('timesheets.store');
        Route::get('/leave-requests', [EmployeeLeaveRequestController::class, 'index'])->name('leave-requests.index');
        Route::post('/leave-requests', [EmployeeLeaveRequestController::class, 'store'])->name('leave-requests.store');
        Route::get('/payroll', [EmployeePayrollController::class, 'index'])->name('payroll.index');
        Route::get('/projects', [\App\Http\Controllers\Employee\ProjectController::class, 'index'])->name('projects.index');
        Route::get('/projects/{project}', [\App\Http\Controllers\Employee\ProjectController::class, 'show'])->name('projects.show');
        Route::post('/projects/{project}/tasks', [\App\Http\Controllers\Employee\ProjectTaskController::class, 'store'])->name('projects.tasks.store');
        Route::patch('/projects/{project}/tasks/{task}', [\App\Http\Controllers\Employee\ProjectTaskController::class, 'update'])->name('projects.tasks.update');
        Route::patch('/projects/{project}/tasks/{task}/start', [\App\Http\Controllers\Employee\ProjectTaskController::class, 'start'])->name('projects.tasks.start');
        Route::delete('/projects/{project}/tasks/{task}', [\App\Http\Controllers\Employee\ProjectTaskController::class, 'destroy'])->name('projects.tasks.destroy');
        Route::get('/projects/{project}/tasks/{task}', [ProjectTaskViewController::class, 'show'])->name('projects.tasks.show');
        Route::post('/projects/{project}/tasks/{task}/subtasks', [\App\Http\Controllers\ProjectTaskSubtaskController::class, 'store'])->name('projects.tasks.subtasks.store');
        Route::patch('/projects/{project}/tasks/{task}/subtasks/{subtask}', [\App\Http\Controllers\ProjectTaskSubtaskController::class, 'update'])->name('projects.tasks.subtasks.update');
        Route::delete('/projects/{project}/tasks/{task}/subtasks/{subtask}', [\App\Http\Controllers\ProjectTaskSubtaskController::class, 'destroy'])->name('projects.tasks.subtasks.destroy');
        Route::get('/projects/{project}/tasks/{task}/activity', [ProjectTaskActivityController::class, 'index'])->name('projects.tasks.activity');
        Route::get('/projects/{project}/tasks/{task}/activity/items', [ProjectTaskActivityController::class, 'items'])->name('projects.tasks.activity.items');
        Route::post('/projects/{project}/tasks/{task}/activity/items', [ProjectTaskActivityController::class, 'storeItem'])
            ->middleware('throttle:10,1')
            ->name('projects.tasks.activity.items.store');
        Route::post('/projects/{project}/tasks/{task}/activity', [ProjectTaskActivityController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('projects.tasks.activity.store');
        Route::post('/projects/{project}/tasks/{task}/subtasks', [\App\Http\Controllers\ProjectTaskSubtaskController::class, 'store'])
            ->name('projects.tasks.subtasks.store');
        Route::patch('/projects/{project}/tasks/{task}/subtasks/{subtask}', [\App\Http\Controllers\ProjectTaskSubtaskController::class, 'update'])
            ->name('projects.tasks.subtasks.update');
        Route::post('/projects/{project}/tasks/{task}/upload', [ProjectTaskActivityController::class, 'upload'])->name('projects.tasks.upload');
        Route::get('/projects/{project}/tasks/{task}/activity/{activity}/attachment', [ProjectTaskActivityController::class, 'attachment'])->name('projects.tasks.activity.attachment');
        Route::get('/projects/{project}/chat', [ProjectChatController::class, 'show'])->name('projects.chat');
        Route::get('/projects/{project}/chat/participants', [ProjectChatController::class, 'participants'])
            ->name('projects.chat.participants');
        Route::get('/projects/{project}/chat/messages', [ProjectChatController::class, 'messages'])->name('projects.chat.messages');
        Route::get('/projects/{project}/chat/stream', [ProjectChatController::class, 'stream'])
            ->name('projects.chat.stream');
        Route::post('/projects/{project}/chat/messages', [ProjectChatController::class, 'storeMessage'])
            ->middleware('throttle:10,1')
            ->name('projects.chat.messages.store');
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
        Route::post('/projects/{project}/tasks/{task}/chat/messages', [ProjectTaskChatController::class, 'storeMessage'])
            ->middleware('throttle:10,1')
            ->name('projects.tasks.chat.messages.store');
        Route::patch('/projects/{project}/tasks/{task}/chat/read', [ProjectTaskChatController::class, 'markRead'])
            ->name('projects.tasks.chat.read');
        Route::post('/projects/{project}/tasks/{task}/chat', [ProjectTaskChatController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('projects.tasks.chat.store');
        Route::get('/projects/{project}/tasks/{task}/messages/{message}/attachment', [ProjectTaskChatController::class, 'attachment'])->name('projects.tasks.messages.attachment');
    });

Route::middleware(['admin', 'user.activity:web', 'nocache'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/tasks', [AdminTasksController::class, 'index'])->name('tasks.index');
    Route::get('/chats', [AdminChatController::class, 'index'])->name('chats.index');
    Route::redirect('/chat', '/admin/chats');
    Route::prefix('hr')->name('hr.')->group(function () {
        Route::get('/dashboard', HrDashboardController::class)->name('dashboard');
        Route::post('employees/{employee}/impersonate', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'impersonate'])->name('employees.impersonate');
        Route::resource('employees', \App\Http\Controllers\Admin\Hr\EmployeeController::class);
        Route::get('employee-payouts/create', [\App\Http\Controllers\Admin\Hr\EmployeePayoutController::class, 'create'])->name('employee-payouts.create');
        Route::post('employee-payouts', [\App\Http\Controllers\Admin\Hr\EmployeePayoutController::class, 'store'])->name('employee-payouts.store');
        Route::get('leave-types', [\App\Http\Controllers\Admin\Hr\LeaveTypeController::class, 'index'])->name('leave-types.index');
        Route::post('leave-types', [\App\Http\Controllers\Admin\Hr\LeaveTypeController::class, 'store'])->name('leave-types.store');
        Route::delete('leave-types/{leaveType}', [\App\Http\Controllers\Admin\Hr\LeaveTypeController::class, 'destroy'])->name('leave-types.destroy');
        Route::get('leave-requests', [\App\Http\Controllers\Admin\Hr\LeaveRequestController::class, 'index'])->name('leave-requests.index');
        Route::post('leave-requests/{leaveRequest}/approve', [\App\Http\Controllers\Admin\Hr\LeaveRequestController::class, 'approve'])->name('leave-requests.approve');
        Route::post('leave-requests/{leaveRequest}/reject', [\App\Http\Controllers\Admin\Hr\LeaveRequestController::class, 'reject'])->name('leave-requests.reject');
        Route::get('timesheets', [\App\Http\Controllers\Admin\Hr\TimesheetController::class, 'index'])->name('timesheets.index');
        Route::post('timesheets/{timesheet}/approve', [\App\Http\Controllers\Admin\Hr\TimesheetController::class, 'approve'])->name('timesheets.approve');
        Route::post('timesheets/{timesheet}/lock', [\App\Http\Controllers\Admin\Hr\TimesheetController::class, 'lock'])->name('timesheets.lock');
        Route::get('payroll', [HrPayrollController::class, 'index'])->name('payroll.index');
        Route::post('payroll/generate', [HrPayrollController::class, 'generate'])->name('payroll.generate');
        Route::get('payroll/{payrollPeriod}', [HrPayrollController::class, 'show'])->name('payroll.show');
        Route::post('payroll/{payrollPeriod}/finalize', [HrPayrollController::class, 'finalize'])->name('payroll.finalize');
        Route::get('payroll/{payrollPeriod}/export', [HrPayrollController::class, 'export'])->name('payroll.export');
    });

    Route::get('employees/summary', [EmployeeSummaryController::class, 'index'])->name('employees.summary');
    Route::get('users/activity-summary', [UserActivitySummaryController::class, 'index'])->name('users.activity-summary');
    Route::get('/automation-status', [AutomationStatusController::class, 'index'])->name('automation-status');
    Route::post('/system/cache/clear', SystemCacheController::class)
        ->name('system.cache.clear');
    Route::get('/profile', [AdminProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [AdminProfileController::class, 'update'])->name('profile.update');
    Route::prefix('user')
        ->name('users.')
        ->middleware('admin.role:master_admin')
        ->group(function () {
            Route::get('{role}', [UserController::class, 'index'])
                ->whereIn('role', ['master_admin', 'sub_admin', 'support'])
                ->name('index');
            Route::get('{role}/create', [UserController::class, 'create'])
                ->whereIn('role', ['master_admin', 'sub_admin', 'support'])
                ->name('create');
            Route::post('{role}', [UserController::class, 'store'])
                ->whereIn('role', ['master_admin', 'sub_admin', 'support'])
                ->name('store');
            Route::get('{user}/edit', [UserController::class, 'edit'])
                ->whereNumber('user')
                ->name('edit');
            Route::put('{user}', [UserController::class, 'update'])
                ->whereNumber('user')
                ->name('update');
            Route::delete('{user}', [UserController::class, 'destroy'])
                ->whereNumber('user')
                ->name('destroy');
    });

    Route::middleware('admin.role:master_admin')
        ->prefix('expenses')
        ->name('expenses.')
        ->group(function () {
            Route::get('/', [AdminExpenseController::class, 'index'])->name('index');
            Route::get('/dashboard', [AdminExpenseDashboardController::class, 'index'])->name('dashboard');
            Route::get('/create', [AdminExpenseController::class, 'create'])->name('create');
            Route::post('/', [AdminExpenseController::class, 'store'])->name('store');
            Route::get('/{expense}/attachment', [AdminExpenseController::class, 'attachment'])->name('attachments.show');
            Route::post('/invoices', [AdminExpenseInvoiceController::class, 'store'])->name('invoices.store');

            Route::get('/categories', [AdminExpenseCategoryController::class, 'index'])->name('categories.index');
            Route::post('/categories', [AdminExpenseCategoryController::class, 'store'])->name('categories.store');
            Route::get('/categories/{category}/edit', [AdminExpenseCategoryController::class, 'edit'])->name('categories.edit');
            Route::put('/categories/{category}', [AdminExpenseCategoryController::class, 'update'])->name('categories.update');
            Route::delete('/categories/{category}', [AdminExpenseCategoryController::class, 'destroy'])->name('categories.destroy');

            Route::get('/recurring', [AdminRecurringExpenseController::class, 'index'])->name('recurring.index');
            Route::get('/recurring/create', [AdminRecurringExpenseController::class, 'create'])->name('recurring.create');
            Route::post('/recurring', [AdminRecurringExpenseController::class, 'store'])->name('recurring.store');
            Route::get('/recurring/{recurringExpense}/edit', [AdminRecurringExpenseController::class, 'edit'])->name('recurring.edit');
            Route::put('/recurring/{recurringExpense}', [AdminRecurringExpenseController::class, 'update'])->name('recurring.update');
            Route::post('/recurring/{recurringExpense}/pause', [AdminRecurringExpenseController::class, 'pause'])->name('recurring.pause');
            Route::post('/recurring/{recurringExpense}/resume', [AdminRecurringExpenseController::class, 'resume'])->name('recurring.resume');
            Route::post('/recurring/{recurringExpense}/stop', [AdminRecurringExpenseController::class, 'stop'])->name('recurring.stop');
            Route::post('/recurring/generate', [AdminRecurringExpenseController::class, 'generate'])->name('recurring.generate');
        });

    Route::middleware('admin.role:master_admin')
        ->prefix('income')
        ->name('income.')
        ->group(function () {
            Route::get('/', [AdminIncomeController::class, 'index'])->name('index');
            Route::get('/create', [AdminIncomeController::class, 'create'])->name('create');
            Route::post('/', [AdminIncomeController::class, 'store'])->name('store');
            Route::get('/{income}/attachment', [AdminIncomeController::class, 'attachment'])->name('attachments.show');

            Route::get('/categories', [AdminIncomeCategoryController::class, 'index'])->name('categories.index');
            Route::post('/categories', [AdminIncomeCategoryController::class, 'store'])->name('categories.store');
            Route::get('/categories/{category}/edit', [AdminIncomeCategoryController::class, 'edit'])->name('categories.edit');
            Route::put('/categories/{category}', [AdminIncomeCategoryController::class, 'update'])->name('categories.update');
            Route::delete('/categories/{category}', [AdminIncomeCategoryController::class, 'destroy'])->name('categories.destroy');
        });

    Route::middleware('admin.role:master_admin')
        ->prefix('finance')
        ->name('finance.')
        ->group(function () {
            Route::get('/reports', [AdminFinanceReportController::class, 'index'])->name('reports.index');

            Route::get('/tax', [AdminFinanceTaxController::class, 'index'])->name('tax.index');
            Route::put('/tax', [AdminFinanceTaxController::class, 'updateSettings'])->name('tax.update');
            Route::post('/tax/rates', [AdminFinanceTaxController::class, 'storeRate'])->name('tax.rates.store');
            Route::get('/tax/rates/{rate}/edit', [AdminFinanceTaxController::class, 'editRate'])->name('tax.rates.edit');
            Route::put('/tax/rates/{rate}', [AdminFinanceTaxController::class, 'updateRate'])->name('tax.rates.update');
            Route::delete('/tax/rates/{rate}', [AdminFinanceTaxController::class, 'destroyRate'])->name('tax.rates.destroy');
        });

    // Legacy route names for backward compatibility with old /admin/admins URLs.
    Route::middleware('admin.role:master_admin')->group(function () {
        Route::get('admins', [UserController::class, 'index'])->defaults('role', 'master_admin')->name('admins.index');
        Route::get('admins/create', [UserController::class, 'create'])->defaults('role', 'master_admin')->name('admins.create');
        Route::post('admins', [UserController::class, 'store'])->defaults('role', 'master_admin')->name('admins.store');
        Route::get('admins/{user}/edit', [UserController::class, 'edit'])->whereNumber('user')->name('admins.edit');
        Route::put('admins/{user}', [UserController::class, 'update'])->whereNumber('user')->name('admins.update');
        Route::delete('admins/{user}', [UserController::class, 'destroy'])->whereNumber('user')->name('admins.destroy');
    });
    Route::resource('customers', CustomerController::class);
    Route::post('customers/{customer}/impersonate', [CustomerController::class, 'impersonate'])->name('customers.impersonate');
    Route::post('customers/{customer}/project-users', [CustomerProjectUserController::class, 'store'])->name('customers.project-users.store');
    Route::get('customers/{customer}/project-users/{user}', [CustomerProjectUserController::class, 'show'])->name('customers.project-users.show');
    Route::put('customers/{customer}/project-users/{user}', [CustomerProjectUserController::class, 'update'])->name('customers.project-users.update');
    Route::delete('customers/{customer}/project-users/{user}', [CustomerProjectUserController::class, 'destroy'])->name('customers.project-users.destroy');
    Route::resource('products', ProductController::class)->except(['show']);
    Route::resource('plans', PlanController::class)->except(['show']);
    Route::resource('subscriptions', SubscriptionController::class)->except(['show']);
    Route::resource('licenses', LicenseController::class)->except(['show']);
    Route::post('licenses/{license}/domains/{domain}/revoke', [LicenseController::class, 'revokeDomain'])->name('licenses.domains.revoke');
    Route::post('licenses/{license}/sync', [LicenseController::class, 'sync'])->name('licenses.sync');
    Route::get('licenses/{license}/sync-status', [LicenseController::class, 'syncStatus'])->name('licenses.sync-status');
    Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/approve', [AdminOrderController::class, 'approve'])->name('orders.approve');
    Route::post('orders/{order}/cancel', [AdminOrderController::class, 'cancel'])->name('orders.cancel');
    Route::patch('orders/{order}/plan', [AdminOrderController::class, 'updatePlan'])->name('orders.plan');
    Route::post('orders/{order}/milestones', [MilestoneController::class, 'store'])->name('orders.milestones.store');
    Route::delete('orders/{order}', [AdminOrderController::class, 'destroy'])->name('orders.destroy');
    Route::resource('projects', ProjectController::class);
    Route::get('projects/{project}/tasks', [ProjectController::class, 'tasks'])->name('projects.tasks.index');
    Route::post('projects/{project}/invoice-remaining', [ProjectController::class, 'invoiceRemainingBudget'])
        ->name('projects.invoice-remaining');
    Route::get('projects/{project}/download/{type}', [ProjectController::class, 'downloadFile'])
        ->where('type', 'contract|proposal')
        ->name('projects.download');
    Route::resource('project-maintenances', \App\Http\Controllers\Admin\ProjectMaintenanceController::class)
        ->except(['show', 'destroy']);
    Route::get('project-maintenances/{projectMaintenance}', [\App\Http\Controllers\Admin\ProjectMaintenanceController::class, 'show'])
        ->name('project-maintenances.show');
    Route::get('projects/{project}/overheads', [\App\Http\Controllers\Admin\ProjectOverheadController::class, 'index'])
        ->name('projects.overheads.index');
    Route::post('projects/{project}/overheads', [\App\Http\Controllers\Admin\ProjectOverheadController::class, 'store'])
        ->name('projects.overheads.store');
    Route::delete('projects/{project}/overheads/{overhead}', [\App\Http\Controllers\Admin\ProjectOverheadController::class, 'destroy'])
        ->name('projects.overheads.destroy');
    Route::post('projects/{project}/overheads/invoice', [\App\Http\Controllers\Admin\ProjectOverheadController::class, 'invoicePending'])
        ->name('projects.overheads.invoice');
    Route::post('projects/{project}/tasks', [\App\Http\Controllers\Admin\ProjectTaskController::class, 'store'])->name('projects.tasks.store');
    Route::patch('projects/{project}/tasks/{task}', [\App\Http\Controllers\Admin\ProjectTaskController::class, 'update'])->name('projects.tasks.update');
    Route::patch('projects/{project}/tasks/{task}/assignees', [\App\Http\Controllers\Admin\ProjectTaskController::class, 'updateAssignees'])
        ->name('projects.tasks.assignees');
    Route::patch('projects/{project}/tasks/{task}/status', [\App\Http\Controllers\Admin\ProjectTaskController::class, 'changeStatus'])->name('projects.tasks.changeStatus');
    Route::delete('projects/{project}/tasks/{task}', [\App\Http\Controllers\Admin\ProjectTaskController::class, 'destroy'])->name('projects.tasks.destroy');
    Route::get('projects/{project}/tasks/{task}', [ProjectTaskViewController::class, 'show'])->name('projects.tasks.show');
    Route::post('projects/{project}/tasks/{task}/subtasks', [\App\Http\Controllers\ProjectTaskSubtaskController::class, 'store'])->name('projects.tasks.subtasks.store');
    Route::patch('projects/{project}/tasks/{task}/subtasks/{subtask}', [\App\Http\Controllers\ProjectTaskSubtaskController::class, 'update'])->name('projects.tasks.subtasks.update');
    Route::delete('projects/{project}/tasks/{task}/subtasks/{subtask}', [\App\Http\Controllers\ProjectTaskSubtaskController::class, 'destroy'])->name('projects.tasks.subtasks.destroy');
    Route::get('projects/{project}/tasks/{task}/activity', [ProjectTaskActivityController::class, 'index'])->name('projects.tasks.activity');
    Route::get('projects/{project}/tasks/{task}/activity/items', [ProjectTaskActivityController::class, 'items'])->name('projects.tasks.activity.items');
    Route::post('projects/{project}/tasks/{task}/activity/items', [ProjectTaskActivityController::class, 'storeItem'])
        ->middleware('throttle:10,1')
        ->name('projects.tasks.activity.items.store');
    Route::post('projects/{project}/tasks/{task}/activity', [ProjectTaskActivityController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('projects.tasks.activity.store');
    Route::post('projects/{project}/tasks/{task}/upload', [ProjectTaskActivityController::class, 'upload'])->name('projects.tasks.upload');
    Route::get('projects/{project}/tasks/{task}/activity/{activity}/attachment', [ProjectTaskActivityController::class, 'attachment'])->name('projects.tasks.activity.attachment');
    Route::get('projects/{project}/chat', [ProjectChatController::class, 'show'])->name('projects.chat');
    Route::get('projects/{project}/chat/participants', [ProjectChatController::class, 'participants'])
        ->name('projects.chat.participants');
    Route::post('projects/{project}/chat/presence', [ProjectChatController::class, 'presence'])
        ->middleware('throttle:60,1')
        ->name('projects.chat.presence');
    Route::get('projects/{project}/chat/stream', [ProjectChatController::class, 'stream'])
        ->name('projects.chat.stream');
    Route::get('projects/{project}/chat/messages', [ProjectChatController::class, 'messages'])->name('projects.chat.messages');
    Route::post('projects/{project}/chat/messages', [ProjectChatController::class, 'storeMessage'])
        ->middleware('throttle:10,1')
        ->name('projects.chat.messages.store');
    Route::patch('projects/{project}/chat/read', [ProjectChatController::class, 'markRead'])
        ->name('projects.chat.read');
    Route::post('projects/{project}/chat', [ProjectChatController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('projects.chat.store');
    Route::get('projects/{project}/chat/messages/{message}/attachment', [ProjectChatController::class, 'attachment'])
        ->name('projects.chat.messages.attachment');
    Route::get('projects/{project}/tasks/{task}/chat', [ProjectTaskChatController::class, 'show'])->name('projects.tasks.chat');
    Route::get('projects/{project}/tasks/{task}/chat/messages', [ProjectTaskChatController::class, 'messages'])->name('projects.tasks.chat.messages');
    Route::post('projects/{project}/tasks/{task}/chat/messages', [ProjectTaskChatController::class, 'storeMessage'])
        ->middleware('throttle:10,1')
        ->name('projects.tasks.chat.messages.store');
    Route::patch('projects/{project}/tasks/{task}/chat/read', [ProjectTaskChatController::class, 'markRead'])
        ->name('projects.tasks.chat.read');
    Route::post('projects/{project}/tasks/{task}/chat', [ProjectTaskChatController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('projects.tasks.chat.store');
    Route::get('projects/{project}/tasks/{task}/messages/{message}/attachment', [ProjectTaskChatController::class, 'attachment'])->name('projects.tasks.messages.attachment');
    Route::resource('sales-reps', \App\Http\Controllers\Admin\SalesRepresentativeController::class)->except(['destroy']);
    Route::post('sales-reps/{sales_rep}/impersonate', [\App\Http\Controllers\Admin\SalesRepresentativeController::class, 'impersonate'])->name('sales-reps.impersonate');
    Route::get('support-tickets', [AdminSupportTicketController::class, 'index'])->name('support-tickets.index');
    Route::get('support-tickets/{ticket}', [AdminSupportTicketController::class, 'show'])->name('support-tickets.show');
    Route::post('support-tickets/{ticket}/reply', [AdminSupportTicketController::class, 'reply'])->name('support-tickets.reply');
    Route::patch('support-tickets/{ticket}/status', [AdminSupportTicketController::class, 'updateStatus'])->name('support-tickets.status');
    Route::patch('support-tickets/{ticket}', [AdminSupportTicketController::class, 'update'])->name('support-tickets.update');
    Route::delete('support-tickets/{ticket}', [AdminSupportTicketController::class, 'destroy'])->name('support-tickets.destroy');
    Route::get('requests', [AdminClientRequestController::class, 'index'])->name('requests.index');
    Route::patch('requests/{clientRequest}', [AdminClientRequestController::class, 'update'])->name('requests.update');
    Route::get('invoices', [AdminInvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/paid', [AdminInvoiceController::class, 'paid'])->name('invoices.paid');
    Route::get('invoices/unpaid', [AdminInvoiceController::class, 'unpaid'])->name('invoices.unpaid');
    Route::get('invoices/overdue', [AdminInvoiceController::class, 'overdue'])->name('invoices.overdue');
    Route::get('invoices/cancelled', [AdminInvoiceController::class, 'cancelled'])->name('invoices.cancelled');
    Route::get('invoices/refunded', [AdminInvoiceController::class, 'refunded'])->name('invoices.refunded');
    Route::get('invoices/{invoice}', [AdminInvoiceController::class, 'show'])->name('invoices.show');
    Route::post('invoices/{invoice}/mark-paid', [AdminInvoiceController::class, 'markPaid'])->name('invoices.mark-paid');
    Route::post('invoices/{invoice}/recalculate', [AdminInvoiceController::class, 'recalculate'])->name('invoices.recalculate');
    Route::put('invoices/{invoice}', [AdminInvoiceController::class, 'update'])->name('invoices.update');
    Route::delete('invoices/{invoice}', [AdminInvoiceController::class, 'destroy'])->name('invoices.destroy');
    Route::post('payment-proofs/{paymentProof}/approve', [AdminPaymentProofController::class, 'approve'])->name('payment-proofs.approve');
    Route::post('payment-proofs/{paymentProof}/reject', [AdminPaymentProofController::class, 'reject'])->name('payment-proofs.reject');
    Route::get('payment-proofs/{paymentProof}/receipt', [AdminPaymentProofController::class, 'receipt'])->name('payment-proofs.receipt');
    Route::get('payment-proofs', [AdminPaymentProofController::class, 'index'])->name('payment-proofs.index');
    Route::get('payment-gateways', [PaymentGatewayController::class, 'index'])->name('payment-gateways.index');
    Route::get('payment-gateways/{paymentGateway}/edit', [PaymentGatewayController::class, 'edit'])->name('payment-gateways.edit');
    Route::put('payment-gateways/{paymentGateway}', [PaymentGatewayController::class, 'update'])->name('payment-gateways.update');
    Route::get('commission-payouts', [CommissionPayoutController::class, 'index'])->name('commission-payouts.index');
    Route::get('commission-payouts/create', [CommissionPayoutController::class, 'create'])->name('commission-payouts.create');
    Route::post('commission-payouts', [CommissionPayoutController::class, 'store'])->name('commission-payouts.store');
    Route::get('commission-payouts/{commissionPayout}', [CommissionPayoutController::class, 'show'])->name('commission-payouts.show');
    Route::post('commission-payouts/{commissionPayout}/pay', [CommissionPayoutController::class, 'markPaid'])->name('commission-payouts.pay');
    Route::post('commission-payouts/{commissionPayout}/reverse', [CommissionPayoutController::class, 'reverse'])->name('commission-payouts.reverse');
    Route::get('commission-earnings/export', [CommissionExportController::class, 'exportEarnings'])->name('commission-earnings.export');
    Route::get('commission-payouts/export', [CommissionExportController::class, 'exportPayouts'])->name('commission-payouts.export');
    Route::get('accounting', [AdminAccountingController::class, 'index'])->name('accounting.index');
    Route::get('accounting/transactions', [AdminAccountingController::class, 'transactions'])->name('accounting.transactions');
    Route::get('accounting/refunds', [AdminAccountingController::class, 'refunds'])->name('accounting.refunds');
    Route::get('accounting/credits', [AdminAccountingController::class, 'credits'])->name('accounting.credits');
    Route::get('accounting/expenses', [AdminAccountingController::class, 'expenses'])->name('accounting.expenses');
    Route::get('accounting/create', [AdminAccountingController::class, 'create'])->name('accounting.create');
    Route::post('accounting', [AdminAccountingController::class, 'store'])->name('accounting.store');
    Route::get('accounting/{entry}/edit', [AdminAccountingController::class, 'edit'])->name('accounting.edit');
    Route::put('accounting/{entry}', [AdminAccountingController::class, 'update'])->name('accounting.update');
    Route::delete('accounting/{entry}', [AdminAccountingController::class, 'destroy'])->name('accounting.destroy');
    Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    Route::get('logs/activity', [SystemLogController::class, 'index'])->name('logs.activity')->defaults('type', 'activity');
    Route::get('logs/admin', [SystemLogController::class, 'index'])->name('logs.admin')->defaults('type', 'admin');
    Route::get('logs/module', [SystemLogController::class, 'index'])->name('logs.module')->defaults('type', 'module');
    Route::get('logs/email', [SystemLogController::class, 'index'])->name('logs.email')->defaults('type', 'email');
    Route::get('logs/ticket-mail-import', [SystemLogController::class, 'index'])->name('logs.ticket-mail-import')->defaults('type', 'ticket-mail-import');
    Route::post('logs/email/{systemLog}/resend', [SystemLogController::class, 'resend'])->name('logs.email.resend');
    Route::delete('logs/email/{systemLog}', [SystemLogController::class, 'destroy'])->name('logs.email.delete');
    
    // Affiliate routes
    Route::resource('affiliates', AdminAffiliateController::class);
    Route::get('affiliates/commissions', [AffiliateCommissionController::class, 'index'])->name('affiliates.commissions.index');
    Route::post('affiliates/commissions/{commission}/approve', [AffiliateCommissionController::class, 'approve'])->name('affiliates.commissions.approve');
    Route::post('affiliates/commissions/{commission}/reject', [AffiliateCommissionController::class, 'reject'])->name('affiliates.commissions.reject');
    Route::post('affiliates/commissions/bulk-approve', [AffiliateCommissionController::class, 'bulkApprove'])->name('affiliates.commissions.bulk-approve');
    Route::get('affiliates/payouts', [AffiliatePayoutController::class, 'index'])->name('affiliates.payouts.index');
    Route::get('affiliates/payouts/create', [AffiliatePayoutController::class, 'create'])->name('affiliates.payouts.create');
    Route::post('affiliates/payouts', [AffiliatePayoutController::class, 'store'])->name('affiliates.payouts.store');
    Route::get('affiliates/payouts/{payout}', [AffiliatePayoutController::class, 'show'])->name('affiliates.payouts.show');
    Route::post('affiliates/payouts/{payout}/complete', [AffiliatePayoutController::class, 'markAsCompleted'])->name('affiliates.payouts.complete');
    Route::delete('affiliates/payouts/{payout}', [AffiliatePayoutController::class, 'destroy'])->name('affiliates.payouts.destroy');
});

Route::middleware(['auth', 'client', 'client.block', 'client.notice', 'user.activity:web', 'project.client', 'nocache'])
    ->prefix('client')
    ->name('client.')
    ->group(function () {
        Route::get('/dashboard', [ClientDashboardController::class, 'index'])->name('dashboard');
        Route::get('/tasks', [ClientTasksController::class, 'index'])->name('tasks.index');
        Route::get('/chats', [ClientChatController::class, 'index'])->name('chats.index');
        Route::redirect('/chat', '/client/chats');
        Route::post('/system/cache/clear', SystemCacheController::class)->name('system.cache.clear');
        Route::get('/orders', [ClientOrderController::class, 'index'])->middleware('project.financial')->name('orders.index');
        Route::get('/orders/review', [ClientOrderController::class, 'review'])->middleware('project.financial')->name('orders.review');
        Route::post('/orders', [ClientOrderController::class, 'store'])->middleware('project.financial')->name('orders.store');
        Route::get('/services', [ClientServiceController::class, 'index'])->middleware('project.financial')->name('services.index');
        Route::get('/services/{subscription}', [ClientServiceController::class, 'show'])->middleware('project.financial')->name('services.show');
        Route::get('/invoices', [ClientInvoiceController::class, 'index'])->middleware('project.financial')->name('invoices.index');
        Route::get('/invoices/paid', [ClientInvoiceController::class, 'paid'])->middleware('project.financial')->name('invoices.paid');
        Route::get('/invoices/unpaid', [ClientInvoiceController::class, 'unpaid'])->middleware('project.financial')->name('invoices.unpaid');
        Route::get('/invoices/overdue', [ClientInvoiceController::class, 'overdue'])->middleware('project.financial')->name('invoices.overdue');
        Route::get('/invoices/cancelled', [ClientInvoiceController::class, 'cancelled'])->middleware('project.financial')->name('invoices.cancelled');
        Route::get('/invoices/refunded', [ClientInvoiceController::class, 'refunded'])->middleware('project.financial')->name('invoices.refunded');
        Route::get('/invoices/{invoice}', [ClientInvoiceController::class, 'show'])->middleware('project.financial')->name('invoices.show');
        Route::get('/profile', [ClientProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [ClientProfileController::class, 'update'])->name('profile.update');
        Route::get('/invoices/{invoice}/pay', [ClientInvoiceController::class, 'pay'])->middleware('project.financial')->name('invoices.pay');
        Route::post('/invoices/{invoice}/checkout', [ClientInvoiceController::class, 'checkout'])->middleware('project.financial')->name('invoices.checkout');
        Route::get('/invoices/{invoice}/manual/{attempt}', [ManualPaymentController::class, 'create'])->middleware('project.financial')->name('invoices.manual');
        Route::post('/invoices/{invoice}/manual/{attempt}', [ManualPaymentController::class, 'store'])->middleware('project.financial')->name('invoices.manual.store');
        Route::get('/invoices/{invoice}/download', [ClientInvoiceController::class, 'download'])->middleware('project.financial')->name('invoices.download');
        Route::get('/domains', [ClientDomainController::class, 'index'])->middleware('project.financial')->name('domains.index');
        Route::get('/domains/{domain}', [ClientDomainController::class, 'show'])->middleware('project.financial')->name('domains.show');
        Route::get('/licenses', [ClientLicenseController::class, 'index'])->middleware('project.financial')->name('licenses.index');
        Route::post('/requests', [ClientRequestController::class, 'store'])->name('requests.store');
        Route::get('/projects', [\App\Http\Controllers\Client\ProjectController::class, 'index'])->name('projects.index');
        Route::get('/projects/{project}', [\App\Http\Controllers\Client\ProjectController::class, 'show'])->name('projects.show');
        Route::post('/projects/{project}/tasks', [\App\Http\Controllers\Client\ProjectTaskController::class, 'store'])->name('projects.tasks.store');
        Route::patch('/projects/{project}/tasks/{task}', [\App\Http\Controllers\Client\ProjectTaskController::class, 'update'])->name('projects.tasks.update');
        Route::get('/projects/{project}/tasks/{task}', [ProjectTaskViewController::class, 'show'])->name('projects.tasks.show');
        Route::post('/projects/{project}/tasks/{task}/subtasks', [\App\Http\Controllers\ProjectTaskSubtaskController::class, 'store'])->name('projects.tasks.subtasks.store');
        Route::patch('/projects/{project}/tasks/{task}/subtasks/{subtask}', [\App\Http\Controllers\ProjectTaskSubtaskController::class, 'update'])->name('projects.tasks.subtasks.update');
        Route::delete('/projects/{project}/tasks/{task}/subtasks/{subtask}', [\App\Http\Controllers\ProjectTaskSubtaskController::class, 'destroy'])->name('projects.tasks.subtasks.destroy');
        Route::get('/projects/{project}/tasks/{task}/activity', [ProjectTaskActivityController::class, 'index'])->name('projects.tasks.activity');
        Route::get('/projects/{project}/tasks/{task}/activity/items', [ProjectTaskActivityController::class, 'items'])->name('projects.tasks.activity.items');
        Route::post('/projects/{project}/tasks/{task}/activity/items', [ProjectTaskActivityController::class, 'storeItem'])
            ->middleware('throttle:10,1')
            ->name('projects.tasks.activity.items.store');
        Route::post('/projects/{project}/tasks/{task}/activity', [ProjectTaskActivityController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('projects.tasks.activity.store');
        Route::post('/projects/{project}/tasks/{task}/subtasks', [\App\Http\Controllers\ProjectTaskSubtaskController::class, 'store'])
            ->name('projects.tasks.subtasks.store');
        Route::patch('/projects/{project}/tasks/{task}/subtasks/{subtask}', [\App\Http\Controllers\ProjectTaskSubtaskController::class, 'update'])
            ->name('projects.tasks.subtasks.update');
        Route::post('/projects/{project}/tasks/{task}/upload', [ProjectTaskActivityController::class, 'upload'])->name('projects.tasks.upload');
        Route::get('/projects/{project}/tasks/{task}/activity/{activity}/attachment', [ProjectTaskActivityController::class, 'attachment'])->name('projects.tasks.activity.attachment');
        Route::get('/projects/{project}/chat', [ProjectChatController::class, 'show'])->name('projects.chat');
        Route::get('/projects/{project}/chat/participants', [ProjectChatController::class, 'participants'])
            ->name('projects.chat.participants');
        Route::get('/projects/{project}/chat/messages', [ProjectChatController::class, 'messages'])->name('projects.chat.messages');
        Route::get('/projects/{project}/chat/stream', [ProjectChatController::class, 'stream'])
            ->name('projects.chat.stream');
        Route::post('/projects/{project}/chat/messages', [ProjectChatController::class, 'storeMessage'])
            ->middleware('throttle:10,1')
            ->name('projects.chat.messages.store');
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
        Route::post('/projects/{project}/tasks/{task}/chat/messages', [ProjectTaskChatController::class, 'storeMessage'])
            ->middleware('throttle:10,1')
            ->name('projects.tasks.chat.messages.store');
        Route::patch('/projects/{project}/tasks/{task}/chat/read', [ProjectTaskChatController::class, 'markRead'])
            ->name('projects.tasks.chat.read');
        Route::post('/projects/{project}/tasks/{task}/chat', [ProjectTaskChatController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('projects.tasks.chat.store');
        Route::get('/projects/{project}/tasks/{task}/messages/{message}/attachment', [ProjectTaskChatController::class, 'attachment'])->name('projects.tasks.messages.attachment');
        Route::get('/support-tickets', [ClientSupportTicketController::class, 'index'])->name('support-tickets.index');
        Route::get('/support-tickets/create', [ClientSupportTicketController::class, 'create'])->name('support-tickets.create');
        Route::post('/support-tickets', [ClientSupportTicketController::class, 'store'])->name('support-tickets.store');
        Route::get('/support-tickets/{ticket}', [ClientSupportTicketController::class, 'show'])->name('support-tickets.show');
        Route::post('/support-tickets/{ticket}/reply', [ClientSupportTicketController::class, 'reply'])->name('support-tickets.reply');
        Route::patch('/support-tickets/{ticket}/status', [ClientSupportTicketController::class, 'updateStatus'])->name('support-tickets.status');
        
        // Affiliate routes
        Route::get('/affiliates', [ClientAffiliateController::class, 'index'])->middleware('project.financial')->name('affiliates.index');
        Route::get('/affiliates/apply', [ClientAffiliateController::class, 'apply'])->middleware('project.financial')->name('affiliates.apply');
        Route::post('/affiliates/apply', [ClientAffiliateController::class, 'storeApplication'])->middleware('project.financial')->name('affiliates.apply.store');
        Route::get('/affiliates/referrals', [ClientAffiliateController::class, 'referrals'])->middleware('project.financial')->name('affiliates.referrals');
        Route::get('/affiliates/commissions', [ClientAffiliateController::class, 'commissions'])->middleware('project.financial')->name('affiliates.commissions');
        Route::get('/affiliates/payouts', [ClientAffiliateController::class, 'payouts'])->middleware('project.financial')->name('affiliates.payouts');
        Route::get('/affiliates/settings', [ClientAffiliateController::class, 'settings'])->middleware('project.financial')->name('affiliates.settings');
        Route::put('/affiliates/settings', [ClientAffiliateController::class, 'updateSettings'])->middleware('project.financial')->name('affiliates.settings.update');
    });

Route::middleware(['salesrep', 'user.activity:sales', 'nocache'])
    ->prefix('sales')
    ->name('rep.')
    ->group(function () {
        Route::post('/logout', [RoleLoginController::class, 'logoutSales'])->name('logout');
        Route::get('/dashboard', SalesRepDashboardController::class)->name('dashboard');
        Route::get('/tasks', [SalesRepTasksController::class, 'index'])->name('tasks.index');
        Route::get('/profile', [SalesRepProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [SalesRepProfileController::class, 'update'])->name('profile.update');
        Route::post('/system/cache/clear', SystemCacheController::class)
            ->name('system.cache.clear');
        Route::get('/earnings', [SalesRepEarningController::class, 'index'])->name('earnings.index');
        Route::get('/payouts', [SalesRepPayoutController::class, 'index'])->name('payouts.index');
        Route::get('/projects', [\App\Http\Controllers\SalesRep\ProjectController::class, 'index'])->name('projects.index');
        Route::get('/projects/{project}', [\App\Http\Controllers\SalesRep\ProjectController::class, 'show'])->name('projects.show');
        Route::post('/projects/{project}/tasks', [\App\Http\Controllers\SalesRep\ProjectTaskController::class, 'store'])->name('projects.tasks.store');
        Route::patch('/projects/{project}/tasks/{task}', [\App\Http\Controllers\SalesRep\ProjectTaskController::class, 'update'])->name('projects.tasks.update');
        Route::delete('/projects/{project}/tasks/{task}', [\App\Http\Controllers\SalesRep\ProjectTaskController::class, 'destroy'])->name('projects.tasks.destroy');
        Route::get('/projects/{project}/tasks/{task}', [ProjectTaskViewController::class, 'show'])->name('projects.tasks.show');
        Route::get('/projects/{project}/tasks/{task}/activity', [ProjectTaskActivityController::class, 'index'])->name('projects.tasks.activity');
        Route::get('/projects/{project}/tasks/{task}/activity/items', [ProjectTaskActivityController::class, 'items'])->name('projects.tasks.activity.items');
        Route::post('/projects/{project}/tasks/{task}/activity/items', [ProjectTaskActivityController::class, 'storeItem'])
            ->middleware('throttle:10,1')
            ->name('projects.tasks.activity.items.store');
        Route::post('/projects/{project}/tasks/{task}/activity', [ProjectTaskActivityController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('projects.tasks.activity.store');
        Route::post('/projects/{project}/tasks/{task}/subtasks', [\App\Http\Controllers\ProjectTaskSubtaskController::class, 'store'])
            ->name('projects.tasks.subtasks.store');
        Route::patch('/projects/{project}/tasks/{task}/subtasks/{subtask}', [\App\Http\Controllers\ProjectTaskSubtaskController::class, 'update'])
            ->name('projects.tasks.subtasks.update');
        Route::delete('/projects/{project}/tasks/{task}/subtasks/{subtask}', [\App\Http\Controllers\ProjectTaskSubtaskController::class, 'destroy'])
            ->name('projects.tasks.subtasks.destroy');
        Route::post('/projects/{project}/tasks/{task}/upload', [ProjectTaskActivityController::class, 'upload'])->name('projects.tasks.upload');
        Route::get('/projects/{project}/tasks/{task}/activity/{activity}/attachment', [ProjectTaskActivityController::class, 'attachment'])->name('projects.tasks.activity.attachment');
        Route::get('/projects/{project}/chat', [ProjectChatController::class, 'show'])->name('projects.chat');
        Route::get('/projects/{project}/chat/participants', [ProjectChatController::class, 'participants'])
            ->name('projects.chat.participants');
        Route::get('/projects/{project}/chat/messages', [ProjectChatController::class, 'messages'])->name('projects.chat.messages');
        Route::get('/projects/{project}/chat/stream', [ProjectChatController::class, 'stream'])
            ->name('projects.chat.stream');
        Route::post('/projects/{project}/chat/messages', [ProjectChatController::class, 'storeMessage'])
            ->middleware('throttle:10,1')
            ->name('projects.chat.messages.store');
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
        Route::post('/projects/{project}/tasks/{task}/chat/messages', [ProjectTaskChatController::class, 'storeMessage'])
            ->middleware('throttle:10,1')
            ->name('projects.tasks.chat.messages.store');
        Route::patch('/projects/{project}/tasks/{task}/chat/read', [ProjectTaskChatController::class, 'markRead'])
            ->name('projects.tasks.chat.read');
        Route::post('/projects/{project}/tasks/{task}/chat', [ProjectTaskChatController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('projects.tasks.chat.store');
        Route::get('/projects/{project}/tasks/{task}/messages/{message}/attachment', [ProjectTaskChatController::class, 'attachment'])->name('projects.tasks.messages.attachment');
    });

Route::middleware(['support', 'user.activity:support', 'nocache'])
    ->prefix('support')
    ->name('support.')
    ->group(function () {
        Route::post('/logout', [RoleLoginController::class, 'logoutSupport'])->name('logout');
        Route::get('/dashboard', SupportDashboardController::class)->name('dashboard');
        Route::get('/tasks', [SupportTasksController::class, 'index'])->name('tasks.index');
        Route::get('/support-tickets', [SupportSupportTicketController::class, 'index'])->name('support-tickets.index');
        Route::get('/support-tickets/{ticket}', [SupportSupportTicketController::class, 'show'])->name('support-tickets.show');
        Route::post('/support-tickets/{ticket}/reply', [SupportSupportTicketController::class, 'reply'])->name('support-tickets.reply');
        Route::patch('/support-tickets/{ticket}/status', [SupportSupportTicketController::class, 'updateStatus'])->name('support-tickets.status');
        Route::patch('/support-tickets/{ticket}', [SupportSupportTicketController::class, 'update'])->name('support-tickets.update');
        Route::delete('/support-tickets/{ticket}', [SupportSupportTicketController::class, 'destroy'])->name('support-tickets.destroy');
    });

Route::get('/products', [PublicProductController::class, 'index'])
    ->name('products.public.index');

Route::get('/{product:slug}/plans/{plan:slug}', [PublicProductController::class, 'showPlan'])
    ->name('products.public.plan');

Route::get('/{product:slug}', [PublicProductController::class, 'show'])
    ->name('products.public.show');
