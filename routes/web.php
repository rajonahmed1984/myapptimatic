<?php

use App\Http\Controllers\Admin\AccountingController as AdminAccountingController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AffiliateCommissionController;
use App\Http\Controllers\Admin\AffiliateController as AdminAffiliateController;
use App\Http\Controllers\Admin\AffiliatePayoutController;
use App\Http\Controllers\Admin\AutomationStatusController;
use App\Http\Controllers\Admin\ClientRequestController as AdminClientRequestController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\InvoiceController as AdminInvoiceController;
use App\Http\Controllers\Admin\PaymentProofController as AdminPaymentProofController;
use App\Http\Controllers\Admin\LicenseController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\PaymentGatewayController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\SupportTicketController as AdminSupportTicketController;
use App\Http\Controllers\Admin\SystemLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Client\AffiliateController as ClientAffiliateController;
use App\Http\Controllers\Client\ClientRequestController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\DomainController as ClientDomainController;
use App\Http\Controllers\Client\InvoiceController as ClientInvoiceController;
use App\Http\Controllers\Client\ManualPaymentController;
use App\Http\Controllers\Client\LicenseController as ClientLicenseController;
use App\Http\Controllers\Client\OrderController as ClientOrderController;
use App\Http\Controllers\Client\ProfileController as ClientProfileController;
use App\Http\Controllers\Client\ServiceController as ClientServiceController;
use App\Http\Controllers\Client\SupportTicketController as ClientSupportTicketController;
use App\Http\Controllers\BrandingAssetController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PaymentCallbackController;
use App\Http\Controllers\PublicProductController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicProductController::class, 'index'])
    ->name('products.public.home');

Route::redirect('/admin', '/admin/login');

Route::get('/branding/{path}', [BrandingAssetController::class, 'show'])
    ->where('path', '.*')
    ->name('branding.asset');

Route::get('/cron/billing', [CronController::class, 'billing'])->name('cron.billing');

Route::match(['GET', 'POST'], '/payments/sslcommerz/{attempt}/success', [PaymentCallbackController::class, 'sslcommerzSuccess'])
    ->name('payments.sslcommerz.success');
Route::match(['GET', 'POST'], '/payments/sslcommerz/{attempt}/fail', [PaymentCallbackController::class, 'sslcommerzFail'])
    ->name('payments.sslcommerz.fail');
Route::match(['GET', 'POST'], '/payments/sslcommerz/{attempt}/cancel', [PaymentCallbackController::class, 'sslcommerzCancel'])
    ->name('payments.sslcommerz.cancel');
Route::get('/payments/paypal/{attempt}/return', [PaymentCallbackController::class, 'paypalReturn'])
    ->name('payments.paypal.return');
Route::get('/payments/paypal/{attempt}/cancel', [PaymentCallbackController::class, 'paypalCancel'])
    ->name('payments.paypal.cancel');
Route::match(['GET', 'POST'], '/payments/bkash/{attempt}/callback', [PaymentCallbackController::class, 'bkashCallback'])
    ->name('payments.bkash.callback');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
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

Route::post('/logout', [AuthController::class, 'logout'])
    ->name('logout')
    ->middleware('auth');
Route::post('/impersonate/stop', [AuthController::class, 'stopImpersonate'])
    ->name('impersonate.stop')
    ->middleware('auth');

Route::middleware(['admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/automation-status', [AutomationStatusController::class, 'index'])->name('automation-status');
    Route::get('/profile', [AdminProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [AdminProfileController::class, 'update'])->name('profile.update');
    Route::resource('admins', AdminUserController::class)->except(['show']);
    Route::resource('customers', CustomerController::class);
    Route::post('customers/{customer}/impersonate', [CustomerController::class, 'impersonate'])->name('customers.impersonate');
    Route::resource('products', ProductController::class)->except(['show']);
    Route::resource('plans', PlanController::class)->except(['show']);
    Route::resource('subscriptions', SubscriptionController::class)->except(['show']);
    Route::resource('licenses', LicenseController::class)->except(['show']);
    Route::post('licenses/{license}/domains/{domain}/revoke', [LicenseController::class, 'revokeDomain'])->name('licenses.domains.revoke');
    Route::post('licenses/{license}/sync', [LicenseController::class, 'sync'])->name('licenses.sync');
    Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/approve', [AdminOrderController::class, 'approve'])->name('orders.approve');
    Route::post('orders/{order}/cancel', [AdminOrderController::class, 'cancel'])->name('orders.cancel');
    Route::patch('orders/{order}/plan', [AdminOrderController::class, 'updatePlan'])->name('orders.plan');
    Route::delete('orders/{order}', [AdminOrderController::class, 'destroy'])->name('orders.destroy');
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
    Route::get('payment-proofs', [AdminPaymentProofController::class, 'index'])->name('payment-proofs.index');
    Route::get('payment-gateways', [PaymentGatewayController::class, 'index'])->name('payment-gateways.index');
    Route::get('payment-gateways/{paymentGateway}/edit', [PaymentGatewayController::class, 'edit'])->name('payment-gateways.edit');
    Route::put('payment-gateways/{paymentGateway}', [PaymentGatewayController::class, 'update'])->name('payment-gateways.update');
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

Route::middleware(['auth', 'client', 'client.notice'])
    ->prefix('client')
    ->name('client.')
    ->group(function () {
        Route::get('/dashboard', [ClientDashboardController::class, 'index'])->name('dashboard');
        Route::get('/orders', [ClientOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/review', [ClientOrderController::class, 'review'])->name('orders.review');
        Route::post('/orders', [ClientOrderController::class, 'store'])->name('orders.store');
        Route::get('/services', [ClientServiceController::class, 'index'])->name('services.index');
        Route::get('/services/{subscription}', [ClientServiceController::class, 'show'])->name('services.show');
        Route::get('/invoices', [ClientInvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/paid', [ClientInvoiceController::class, 'paid'])->name('invoices.paid');
        Route::get('/invoices/unpaid', [ClientInvoiceController::class, 'unpaid'])->name('invoices.unpaid');
        Route::get('/invoices/overdue', [ClientInvoiceController::class, 'overdue'])->name('invoices.overdue');
        Route::get('/invoices/cancelled', [ClientInvoiceController::class, 'cancelled'])->name('invoices.cancelled');
        Route::get('/invoices/refunded', [ClientInvoiceController::class, 'refunded'])->name('invoices.refunded');
        Route::get('/invoices/{invoice}', [ClientInvoiceController::class, 'show'])->name('invoices.show');
        Route::get('/profile', [ClientProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [ClientProfileController::class, 'update'])->name('profile.update');
        Route::get('/invoices/{invoice}/pay', [ClientInvoiceController::class, 'pay'])->name('invoices.pay');
        Route::post('/invoices/{invoice}/checkout', [ClientInvoiceController::class, 'checkout'])->name('invoices.checkout');
        Route::get('/invoices/{invoice}/manual/{attempt}', [ManualPaymentController::class, 'create'])->name('invoices.manual');
        Route::post('/invoices/{invoice}/manual/{attempt}', [ManualPaymentController::class, 'store'])->name('invoices.manual.store');
        Route::get('/invoices/{invoice}/download', [ClientInvoiceController::class, 'download'])->name('invoices.download');
        Route::get('/domains', [ClientDomainController::class, 'index'])->name('domains.index');
        Route::get('/domains/{domain}', [ClientDomainController::class, 'show'])->name('domains.show');
        Route::get('/licenses', [ClientLicenseController::class, 'index'])->name('licenses.index');
        Route::post('/requests', [ClientRequestController::class, 'store'])->name('requests.store');
        Route::get('/support-tickets', [ClientSupportTicketController::class, 'index'])->name('support-tickets.index');
        Route::get('/support-tickets/create', [ClientSupportTicketController::class, 'create'])->name('support-tickets.create');
        Route::post('/support-tickets', [ClientSupportTicketController::class, 'store'])->name('support-tickets.store');
        Route::get('/support-tickets/{ticket}', [ClientSupportTicketController::class, 'show'])->name('support-tickets.show');
        Route::post('/support-tickets/{ticket}/reply', [ClientSupportTicketController::class, 'reply'])->name('support-tickets.reply');
        Route::patch('/support-tickets/{ticket}/status', [ClientSupportTicketController::class, 'updateStatus'])->name('support-tickets.status');
        
        // Affiliate routes
        Route::get('/affiliates', [ClientAffiliateController::class, 'index'])->name('affiliates.index');
        Route::get('/affiliates/apply', [ClientAffiliateController::class, 'apply'])->name('affiliates.apply');
        Route::post('/affiliates/apply', [ClientAffiliateController::class, 'storeApplication'])->name('affiliates.apply.store');
        Route::get('/affiliates/referrals', [ClientAffiliateController::class, 'referrals'])->name('affiliates.referrals');
        Route::get('/affiliates/commissions', [ClientAffiliateController::class, 'commissions'])->name('affiliates.commissions');
        Route::get('/affiliates/payouts', [ClientAffiliateController::class, 'payouts'])->name('affiliates.payouts');
        Route::get('/affiliates/settings', [ClientAffiliateController::class, 'settings'])->name('affiliates.settings');
        Route::put('/affiliates/settings', [ClientAffiliateController::class, 'updateSettings'])->name('affiliates.settings.update');
    });

Route::get('/products', [PublicProductController::class, 'index'])
    ->name('products.public.index');

Route::get('/{product:slug}/plans/{plan:slug}', [PublicProductController::class, 'showPlan'])
    ->name('products.public.plan');

Route::get('/{product:slug}', [PublicProductController::class, 'show'])
    ->name('products.public.show');
