<?php
use App\Services\WallesterService;

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\EmailValidationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Dashboard\AccountPayableController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Dashboard\CustomerController;
use App\Http\Controllers\Dashboard\RoleController;
use App\Http\Controllers\Dashboard\SettingsController;
use App\Http\Controllers\Dashboard\StripeFeeController;
use App\Http\Controllers\Dashboard\CustomerTpaController;
use App\Http\Controllers\Dashboard\UserController;
use App\Http\Controllers\Dashboard\AuditLogController;

// ==================== AUTH ====================
Route::middleware('web')->group(function () {
    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});

// ==================== DASHBOARD ====================
Route::prefix('dashboard')->name('dashboard')->middleware(['web', 'auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('');

    // Customers
    Route::get('/customers', [CustomerController::class, 'index'])->name('.customers')->middleware('can:customers.view');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('.customers.show')->middleware('can:customers.view');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('.customers.update')->middleware('can:customers.edit');
    Route::middleware('can:customers.view')->group(function () {
        Route::delete('/customers/{customer}/tpas/{tpa}', [CustomerTpaController::class, 'destroy'])->name('.customers.tpas.destroy');
        Route::post('/customers/{customer}/tpas/{tpa}/fees', [CustomerTpaController::class, 'storeFee'])->name('.customers.tpas.fees.store');
        Route::delete('/customers/{customer}/tpas/{tpa}/fees/{fee}', [CustomerTpaController::class, 'destroyFee'])->name('.customers.tpas.fees.destroy');
    });

    // Users CRUD
    Route::middleware('can:users.view')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('.users');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('.users.show');
    });
    Route::middleware('can:users.create')->group(function () {
        Route::get('/users/create', [UserController::class, 'create'])->name('.users.create');
        Route::post('/users', [UserController::class, 'store'])->name('.users.store');
    });
    Route::middleware('can:users.edit')->group(function () {
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('.users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('.users.update');
    });
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('.users.destroy')->middleware('can:users.delete');

    // Roles CRUD
    Route::middleware('can:roles.view')->group(function () {
        Route::get('/roles', [RoleController::class, 'index'])->name('.roles');
        Route::get('/roles/{role}', [RoleController::class, 'show'])->name('.roles.show');
    });
    Route::middleware('can:roles.create')->group(function () {
        Route::get('/roles/create', [RoleController::class, 'create'])->name('.roles.create');
        Route::post('/roles', [RoleController::class, 'store'])->name('.roles.store');
    });
    Route::middleware('can:roles.edit')->group(function () {
        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('.roles.edit');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('.roles.update');
    });
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('.roles.destroy')->middleware('can:roles.delete');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('.settings')->middleware('can:settings.view');
    Route::post('/settings', [SettingsController::class, 'update'])->name('.settings.update')->middleware('can:settings.edit');

    // Stripe Fees
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

    // Accounts Payable
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

    // Audit Logs
    Route::middleware('can:audit_logs.view')->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('.audit-logs');
        Route::get('/audit-logs/{activity}', [AuditLogController::class, 'show'])->name('.audit-logs.show');
    });

    // Notifications (JSON — chamados via fetch pelo navbar)
    Route::get('/notifications', [\App\Http\Controllers\Dashboard\NotificationController::class, 'index'])->name('.notifications');
    Route::post('/notifications/{notification}/read', [\App\Http\Controllers\Dashboard\NotificationController::class, 'markRead'])->name('.notifications.read');
    Route::post('/notifications/read-all', [\App\Http\Controllers\Dashboard\NotificationController::class, 'markAllRead'])->name('.notifications.read-all');
});

// Rotas de verificação de e-mail
Route::get('email/verify', function () {
    return view('auth.verify-email');
})->middleware('auth')->name('verification.notice');


// Rota de verificação de e-mail assinada
Route::get('email/verify/{id}/{hash}', [EmailValidationController::class, 'verifyFrontend'])
    ->middleware(['signed'])
    ->name('verification.verify');


Route::get('email/validated', [EmailValidationController::class, 'show'])->name('email.validated');

// Página de pagamento para integrações (iframe / redirect)
Route::get('/pay/{uuid}', [\App\Http\Controllers\Pay\PayController::class, 'show'])
    ->where('uuid', '[0-9a-f\-]{36}')
    ->name('pay.show');

Route::post('email/verification-notification', function () {
    request()->user()->sendEmailVerificationNotification();
    return back()->with('message', 'Verification link sent!');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');


Route::get('/test-wallester-account', function (WallesterService $wallesterService) {
    $data = [
        'name' => 'Daniel Silva',
    ];
    try {
        $result = $wallesterService->createAccount($data);
        return response()->json($result);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
});

Route::get('/test/stripe-funding-instructions', function (\App\Clients\StripeClient $stripeClient) {
    $data = [
        'funding_type' => 'bank_transfer',
    'bank_transfer' => ['type' => 'us_bank_transfer'],
    'currency' => 'usd',
    ];
    try {
        $result = $stripeClient->createCustomerFundingInstructions('cus_USJQLXo86JyvnJ', $data);
        return response()->json($result);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
});
