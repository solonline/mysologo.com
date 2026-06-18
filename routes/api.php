<?php

use App\Domains\Auth\Controllers\AuthController;
use App\Domains\Auth\Controllers\PinController;
use App\Domains\Kyc\Controllers\DiditWebhookController;
use App\Domains\Kyc\Controllers\IdentityVerificationController;
use App\Domains\Onboarding\Controllers\PhoneVerificationController;
use App\Domains\Settings\Controllers\SettingController;
use App\Domains\Settings\Controllers\SystemInfoController;
use App\Domains\Tpa\Controllers\TpaController;
use App\Domains\Customer\Controllers\CustomerController;
use App\Domains\Customer\Controllers\NotificationController;
use App\Domains\Merchant\Controllers\CustomerMerchantController;
use App\Domains\Merchant\Controllers\MerchantController;
use App\Domains\Wallet\Controllers\WalletAccountController;
use App\Domains\Wallet\Controllers\WalletDashboardController;
use App\Domains\Wallet\Controllers\WalletTransactionController;
use App\Domains\Ebury\Controllers\EburyWebhookController;
use App\Domains\Stripe\Controllers\StripeController;
use App\Domains\Stripe\Controllers\StripeWebhookController;
use App\Domains\Wallester\Controllers\WallesterWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Rotas legadas (v1). Para testar o domínio Auth modularizado, use /api/v2/auth/*.
| Ver app/Domains/Auth/Routes/auth.php
*/

Route::post('/register-retail', [AuthController::class, 'registerRetail']);
Route::post('/register-corporate', [AuthController::class, 'registerCorporate']);
Route::post('/create-didit-session', [IdentityVerificationController::class, 'createSession'])->middleware('auth:sanctum');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/login-pin', [AuthController::class, 'loginWithPin']);
Route::post('/login-tpa', [AuthController::class, 'loginWithTpa']);
//primeiro login do tpa, para linkar a conta, depois disso o login é feito com pin ou email/senha, e o tpa fica como método de pagamento vinculado na conta do cliente
Route::post('/tpa/link', [TpaController::class, 'link']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/webhooks/didit', [DiditWebhookController::class, 'handle'])->name('didit.webhook');
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');
Route::post('/webhooks/wallester', [WallesterWebhookController::class, 'handle'])->name('wallester.webhook');
Route::post('/webhooks/ebury', [EburyWebhookController::class, 'handle'])->name('ebury.webhook');

// Merchant API — autenticação via X-Api-Key
Route::middleware(['auth.merchant', 'throttle:stripe-intent'])->prefix('merchant')->group(function () {
    Route::post('/payment-requests', [MerchantController::class, 'createPaymentRequest']);
    Route::post('/terminal/connection-token', [StripeController::class, 'terminalConnectionToken']);
    Route::post('/terminal/create-intent', [StripeController::class, 'createTerminal'])->middleware('idempotency');
});
Route::get('/basic-system-info', [SystemInfoController::class, 'get']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/merchants/register', [CustomerMerchantController::class, 'register']);

    $customerEligibilityMiddlewares = [
        'customer.active',
        'customer.email.verified',
        'customer.document.approved',
    ];

    Route::group(['prefix' => 'settings'], function () {
        Route::get('/', [SettingController::class, 'index']);
        Route::get('/{key}', [SettingController::class, 'get'])->where('key', '[a-z_.]+');
        Route::post('/{key}', [SettingController::class, 'set'])->where('key', '[a-z_.]+');
    });

    Route::group(['prefix' => 'customer'], function () {
        Route::get('/me', [CustomerController::class, 'me']);
        Route::post('/update', [CustomerController::class, 'update']);
        Route::post('/create-corporate-responsible', [CustomerController::class, 'createResponsible']);
        Route::post('/submit-corporate-documents', [CustomerController::class, 'submitCorporateDocuments']);
        Route::post('/send-validation-email', [CustomerController::class, 'sendValidationEmail']);
        Route::post('/upload-avatar', [CustomerController::class, 'uploadAvatar']);
        Route::get('/find/{username}', [CustomerController::class, 'findByUsername'])->where('username', '[a-zA-Z0-9_]+');
        // Phone Verification (legado v1) — v2: /api/v2/onboarding/phone/*
        Route::post('/phone/send', [PhoneVerificationController::class, 'send']);
        Route::post('/phone/validate', [PhoneVerificationController::class, 'validateCode']);

    });

    Route::middleware($customerEligibilityMiddlewares)->group(function () {
        Route::post('/create-pin', [PinController::class, 'store']);



        Route::group(['prefix' => 'dashboard'], function () {
            Route::get('/summary', [WalletDashboardController::class, 'summary']);
        });

        Route::get('/dashboard', [WalletDashboardController::class, 'summary']);

        Route::group(['prefix' => 'stripe'], function () {
            Route::get('/payment-methods', [StripeController::class, 'paymentMethods']);
            Route::get('/fee-estimates', [StripeController::class, 'feeEstimates']);
            Route::post('/create-intent-payment', [StripeController::class, 'create'])->middleware(['idempotency', 'throttle:stripe-intent']);
            Route::post('/create-funding', [StripeController::class, 'createFunding'])->middleware('throttle:stripe-intent');
            Route::get('/get-funding-instructions', [StripeController::class, 'getFundingInstructions']);

            Route::post('/terminal/{transaction}/capture', [StripeController::class, 'captureTerminal'])->where('transaction', '[0-9]+')->middleware('throttle:stripe-intent');
        });

        Route::group(['prefix' => 'account'], function () {
            Route::get('/', [WalletAccountController::class, 'index']);
            Route::get('/balance', [WalletAccountController::class, 'balance']);
        });

        Route::group(['prefix' => 'transactions'], function () {
            Route::get('/', [WalletTransactionController::class, 'index']);
            Route::get('/{id}', [WalletTransactionController::class, 'show'])->where('id', '[0-9]+');
            Route::post('/p2p', [WalletTransactionController::class, 'p2p']);
        });

        // Notifications
        Route::group(['prefix' => 'notifications'], function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::post('/{notification}/read', [NotificationController::class, 'markRead']);
            Route::post('/read-all', [NotificationController::class, 'markAllRead']);
        });
    });
});
