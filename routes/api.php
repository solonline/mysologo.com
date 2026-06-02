<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\PinController;
use App\Http\Controllers\Api\CustomerMerchantController;
use App\Http\Controllers\Api\MerchantController;
use App\Http\Controllers\Webhooks\DiditWebhookController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;


Route::post('/register-retail', [AuthController::class, 'registerRetail']);
Route::post('/register-corporate', [AuthController::class, 'registerCorporate']);
Route::post('/create-didit-session', [AuthController::class, 'createDiditSession']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/login-pin', [AuthController::class, 'loginWithPin']);
Route::post('/login-tpa', [AuthController::class, 'loginWithTpa']);
//primeiro login do tpa, para linkar a conta, depois disso o login é feito com pin ou email/senha, e o tpa fica como método de pagamento vinculado na conta do cliente
Route::post('/tpa/link', [\App\Http\Controllers\Api\TpaController::class, 'link']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/webhooks/didit', [DiditWebhookController::class, 'handle'])->name('didit.webhook');
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');

// Merchant API — autenticação via X-Api-Key
Route::middleware(['auth.merchant', 'throttle:stripe-intent'])->prefix('merchant')->group(function () {
    Route::post('/payment-requests', [MerchantController::class, 'createPaymentRequest']);
    Route::post('/terminal/connection-token', [\App\Http\Controllers\Api\StripeController::class, 'terminalConnectionToken']);
    Route::post('/terminal/create-intent', [\App\Http\Controllers\Api\StripeController::class, 'createTerminal'])->middleware('idempotency');
});
Route::get('/basic-system-info', [\App\Http\Controllers\Api\BasicSystemInfo::class, 'get']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/merchants/register', [CustomerMerchantController::class, 'register']);

    $customerEligibilityMiddlewares = [
        'customer.active',
        'customer.email.verified',
        'customer.document.approved',
    ];

    Route::group(['prefix' => 'settings'], function () {
        Route::get('/', [\App\Http\Controllers\Api\SettingController::class, 'index']);
        Route::get('/{key}', [\App\Http\Controllers\Api\SettingController::class, 'get'])->where('key', '[a-z_]+');
        Route::post('/{key}', [\App\Http\Controllers\Api\SettingController::class, 'set'])->where('key', '[a-z_]+');
    });

    Route::group(['prefix' => 'customer'], function () {
        Route::get('/me', [\App\Http\Controllers\Api\CustomerController::class, 'me']);
        Route::post('/update', [\App\Http\Controllers\Api\CustomerController::class, 'update']);
        Route::post('/create-corporate-responsible', [\App\Http\Controllers\Api\CustomerController::class, 'createResponsible']);
        Route::post('/submit-corporate-documents', [\App\Http\Controllers\Api\CustomerController::class, 'submitCorporateDocuments']);
        Route::post('/send-validation-email', [\App\Http\Controllers\Api\CustomerController::class, 'sendValidationEmail']);
        Route::post('/upload-avatar', [\App\Http\Controllers\Api\CustomerController::class, 'uploadAvatar']);
        Route::get('/find/{username}', [\App\Http\Controllers\Api\CustomerController::class, 'findByUsername'])->where('username', '[a-zA-Z0-9_]+');
         // Phone Verification
        Route::post('/phone/send', [\App\Http\Controllers\Api\Auth\PhoneVerificationController::class, 'send']);
        Route::post('/phone/validate', [\App\Http\Controllers\Api\Auth\PhoneVerificationController::class, 'validateCode']);

    });

    Route::middleware($customerEligibilityMiddlewares)->group(function () {
        Route::post('/create-pin', [PinController::class, 'store']);



        Route::group(['prefix' => 'dashboard'], function () {
            Route::get('/summary', [\App\Http\Controllers\Api\DashboardCustomerController::class, 'summary']);
        });

        Route::get('/dashboard', [\App\Http\Controllers\Api\DashboardCustomerController::class, 'summary']);

        Route::group(['prefix' => 'stripe'], function () {
            Route::get('/payment-methods', [\App\Http\Controllers\Api\StripeController::class, 'paymentMethods']);
            Route::get('/fee-estimates', [\App\Http\Controllers\Api\StripeController::class, 'feeEstimates']);
            Route::post('/create-intent-payment', [\App\Http\Controllers\Api\StripeController::class, 'create'])->middleware(['idempotency', 'throttle:stripe-intent']);
            Route::post('/create-funding', [\App\Http\Controllers\Api\StripeController::class, 'createFunding'])->middleware('throttle:stripe-intent');
            Route::get('/get-funding-instructions', [\App\Http\Controllers\Api\StripeController::class, 'getFundingInstructions']);

            Route::post('/terminal/{transaction}/capture', [\App\Http\Controllers\Api\StripeController::class, 'captureTerminal'])->where('transaction', '[0-9]+')->middleware('throttle:stripe-intent');
        });

        Route::group(['prefix' => 'account'], function () {
            Route::get('/', [\App\Http\Controllers\Api\AccountController::class, 'index']);
            Route::get('/balance', [\App\Http\Controllers\Api\AccountController::class, 'balance']);
        });

        Route::group(['prefix' => 'transactions'], function () {
            Route::get('/', [\App\Http\Controllers\Api\TransactionController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Api\TransactionController::class, 'show'])->where('id', '[0-9]+');
            Route::post('/p2p', [\App\Http\Controllers\Api\TransactionController::class, 'p2p']);
        });

        // Notifications
        Route::group(['prefix' => 'notifications'], function () {
            Route::get('/', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
            Route::post('/{notification}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markRead']);
            Route::post('/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllRead']);
        });
    });
});
