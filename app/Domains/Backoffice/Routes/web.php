<?php

use App\Domains\Backoffice\Controllers\AccountPayableController;
use App\Domains\Backoffice\Controllers\AuditLogController;
use App\Domains\Backoffice\Controllers\Auth\LoginController;
use App\Domains\Backoffice\Controllers\CustomerController;
use App\Domains\Backoffice\Controllers\CustomerMerchantFeeController;
use App\Domains\Backoffice\Controllers\CustomerTpaController;
use App\Domains\Backoffice\Controllers\DashboardController;
use App\Domains\Backoffice\Controllers\NotificationController;
use App\Domains\Backoffice\Controllers\OperationsController;
use App\Domains\Backoffice\Controllers\PlatformRevenueController;
use App\Domains\Backoffice\Controllers\RoleController;
use App\Domains\Backoffice\Controllers\SettingsController;
use App\Domains\Backoffice\Controllers\StripeFeeController;
use App\Domains\Backoffice\Controllers\TransactionController;
use App\Domains\Backoffice\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas web do domínio Backoffice (staff)
|--------------------------------------------------------------------------
| Carregadas via routes/web.php com middleware `web`.
| Nomes de rotas mantidos (login, dashboard.*) para compatibilidade com Blade.
*/

// ==================== AUTH STAFF ====================
Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ==================== DASHBOARD ====================
Route::prefix('dashboard')->name('dashboard')->middleware('auth:web')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('');

    Route::get('/customers', [CustomerController::class, 'index'])->name('.customers')->middleware('can:customers.view');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('.customers.show')->middleware('can:customers.view');
    Route::get('/customers/{customer}/transactions', [TransactionController::class, 'customer'])->name('.customers.transactions')->middleware('can:customers.view');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('.customers.update')->middleware('can:customers.edit');
    Route::post('/customers/{customer}/phone', [CustomerController::class, 'requestPhoneChange'])->name('.customers.phone.request')->middleware('can:customers.edit');
    Route::post('/customers/{customer}/phone/resend', [CustomerController::class, 'resendPhoneChange'])->name('.customers.phone.resend')->middleware('can:customers.edit');
    Route::post('/customers/{customer}/phone/cancel', [CustomerController::class, 'cancelPhoneChange'])->name('.customers.phone.cancel')->middleware('can:customers.edit');
    Route::post('/customers/{customer}/phone/confirm', [CustomerController::class, 'confirmPhoneChange'])->name('.customers.phone.confirm')->middleware('can:customers.edit');
    Route::post('/customers/{customer}/retry-provisioning', [CustomerController::class, 'retryProvisioning'])->name('.customers.retry-provisioning')->middleware('can:customers.edit');
    Route::post('/customers/{customer}/retry-stripe-funding', [CustomerController::class, 'retryStripeFunding'])->name('.customers.retry-stripe-funding')->middleware('can:customers.edit');
    Route::post('/customers/{customer}/balance-adjustments', [CustomerController::class, 'requestBalanceAdjustment'])->name('.customers.balance-adjustments.request');
    Route::post('/customers/{customer}/balance-adjustments/{adjustment}/confirm', [CustomerController::class, 'confirmBalanceAdjustment'])->name('.customers.balance-adjustments.confirm');
    Route::post('/customers/{customer}/balance-adjustments/{adjustment}/resend', [CustomerController::class, 'resendBalanceAdjustment'])->name('.customers.balance-adjustments.resend');
    Route::post('/customers/{customer}/balance-adjustments/{adjustment}/cancel', [CustomerController::class, 'cancelBalanceAdjustment'])->name('.customers.balance-adjustments.cancel');
    Route::middleware('can:customers.view')->group(function () {
        Route::delete('/customers/{customer}/tpas/{tpa}', [CustomerTpaController::class, 'destroy'])->name('.customers.tpas.destroy');
        Route::post('/customers/{customer}/tpas/{tpa}/fees', [CustomerTpaController::class, 'storeFee'])->name('.customers.tpas.fees.store');
        Route::delete('/customers/{customer}/tpas/{tpa}/fees/{fee}', [CustomerTpaController::class, 'destroyFee'])->name('.customers.tpas.fees.destroy');
        Route::post('/customers/{customer}/merchants/{merchant}/fees', [CustomerMerchantFeeController::class, 'storeFee'])->name('.customers.merchants.fees.store');
        Route::delete('/customers/{customer}/merchants/{merchant}/fees/{fee}', [CustomerMerchantFeeController::class, 'destroyFee'])->name('.customers.merchants.fees.destroy');
    });

    Route::middleware('can:transactions.view')->group(function () {
        Route::get('/transactions', [TransactionController::class, 'index'])->name('.transactions');
        Route::get('/platform-revenue', [PlatformRevenueController::class, 'index'])->name('.platform-revenue');
    });

    Route::middleware('can:users.view')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('.users');
    });
    Route::middleware('can:users.create')->group(function () {
        Route::get('/users/create', [UserController::class, 'create'])->name('.users.create');
        Route::post('/users', [UserController::class, 'store'])->name('.users.store');
    });
    Route::middleware('can:users.view')->group(function () {
        Route::get('/users/{user}', [UserController::class, 'show'])
            ->whereNumber('user')
            ->name('.users.show');
    });
    Route::middleware('can:users.edit')->group(function () {
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])
            ->whereNumber('user')
            ->name('.users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])
            ->whereNumber('user')
            ->name('.users.update');
    });
    Route::delete('/users/{user}', [UserController::class, 'destroy'])
        ->whereNumber('user')
        ->name('.users.destroy')
        ->middleware('can:users.delete');

    Route::middleware('can:roles.view')->group(function () {
        Route::get('/roles', [RoleController::class, 'index'])->name('.roles');
    });
    Route::middleware('can:roles.create')->group(function () {
        Route::get('/roles/create', [RoleController::class, 'create'])->name('.roles.create');
        Route::post('/roles', [RoleController::class, 'store'])->name('.roles.store');
    });
    Route::middleware('can:roles.view')->group(function () {
        Route::get('/roles/{role}', [RoleController::class, 'show'])
            ->whereNumber('role')
            ->name('.roles.show');
    });
    Route::middleware('can:roles.edit')->group(function () {
        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])
            ->whereNumber('role')
            ->name('.roles.edit');
        Route::put('/roles/{role}', [RoleController::class, 'update'])
            ->whereNumber('role')
            ->name('.roles.update');
    });
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])
        ->whereNumber('role')
        ->name('.roles.destroy')
        ->middleware('can:roles.delete');

    Route::get('/settings', [SettingsController::class, 'index'])->name('.settings')->middleware('can:settings.view');
    Route::post('/settings', [SettingsController::class, 'update'])->name('.settings.update')->middleware('can:settings.edit');
    Route::post('/settings/test-mail', [SettingsController::class, 'testMail'])
        ->name('.settings.test-mail')
        ->middleware('can:settings.edit');
    Route::post('/operations/retry-wallester-topups', [OperationsController::class, 'retryWallesterTopups'])
        ->name('.operations.retry-wallester-topups')
        ->middleware('can:settings.edit');

    Route::middleware('can:stripe_fees.view')->group(function () {
        Route::get('/stripe-fees', [StripeFeeController::class, 'index'])->name('.stripe-fees');
    });
    Route::middleware('can:stripe_fees.create')->group(function () {
        Route::get('/stripe-fees/create', [StripeFeeController::class, 'create'])->name('.stripe-fees.create');
        Route::post('/stripe-fees', [StripeFeeController::class, 'store'])->name('.stripe-fees.store');
    });
    Route::middleware('can:stripe_fees.edit')->group(function () {
        Route::get('/stripe-fees/{stripeFee}/edit', [StripeFeeController::class, 'edit'])->name('.stripe-fees.edit');
        Route::put('/stripe-fees/{stripeFee}', [StripeFeeController::class, 'update'])->name('.stripe-fees.update');
    });
    Route::delete('/stripe-fees/{stripeFee}', [StripeFeeController::class, 'destroy'])->name('.stripe-fees.destroy')->middleware('can:stripe_fees.delete');

    Route::middleware('can:accounts_payable.view')->group(function () {
        Route::get('/accounts-payable', [AccountPayableController::class, 'index'])->name('.accounts-payable');
        Route::get('/accounts-payable/{accountPayable}/edit', [AccountPayableController::class, 'edit'])->name('.accounts-payable.edit');
        Route::get('/accounts-payable/{accountPayable}/attachments/{attachment}', [AccountPayableController::class, 'downloadAttachment'])->name('.accounts-payable.attachments.download');
    });
    Route::middleware('can:accounts_payable.create')->group(function () {
        Route::get('/accounts-payable/create', [AccountPayableController::class, 'create'])->name('.accounts-payable.create');
        Route::post('/accounts-payable', [AccountPayableController::class, 'store'])->name('.accounts-payable.store');
    });
    Route::middleware('can:accounts_payable.edit')->group(function () {
        Route::put('/accounts-payable/{accountPayable}', [AccountPayableController::class, 'update'])->name('.accounts-payable.update');
        Route::post('/accounts-payable/{accountPayable}/attachments', [AccountPayableController::class, 'storeAttachment'])->name('.accounts-payable.attachments.store');
        Route::delete('/accounts-payable/{accountPayable}/attachments/{attachment}', [AccountPayableController::class, 'destroyAttachment'])->name('.accounts-payable.attachments.destroy');
        Route::post('/accounts-payable/{accountPayable}/cancel', [AccountPayableController::class, 'cancel'])->name('.accounts-payable.cancel');
    });
    Route::post('/accounts-payable/{accountPayable}/paid', [AccountPayableController::class, 'markPaid'])->name('.accounts-payable.paid')->middleware('can:accounts_payable.pay');
    Route::delete('/accounts-payable/{accountPayable}', [AccountPayableController::class, 'destroy'])->name('.accounts-payable.destroy')->middleware('can:accounts_payable.delete');

    Route::middleware('can:audit_logs.view')->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('.audit-logs');
        Route::get('/audit-logs/{activity}', [AuditLogController::class, 'show'])->name('.audit-logs.show');
    });

    Route::get('/notifications', [NotificationController::class, 'index'])->name('.notifications');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('.notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('.notifications.read-all');
});
