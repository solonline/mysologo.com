<?php

use App\Domains\Merchant\Controllers\CustomerMerchantController;
use App\Domains\Merchant\Controllers\MerchantController;
use App\Domains\Stripe\Controllers\StripeController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'domain' => 'merchant',
    'status' => 'ok',
]))->name('merchant.health');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/', [CustomerMerchantController::class, 'index'])
        ->name('merchant.index');
    Route::get('/payment-requests', [CustomerMerchantController::class, 'paymentRequests'])
        ->name('merchant.customer.payment-requests.index');
    Route::post('/register', [CustomerMerchantController::class, 'register'])
        ->name('merchant.register');
    Route::patch('/{merchant}', [CustomerMerchantController::class, 'update'])
        ->where('merchant', '[0-9]+')
        ->name('merchant.customer.update');
    Route::get('/{merchant}/credentials', [CustomerMerchantController::class, 'credentials'])
        ->where('merchant', '[0-9]+')
        ->name('merchant.customer.credentials.show');
    Route::post('/{merchant}/credentials/rotate', [CustomerMerchantController::class, 'rotateCredentials'])
        ->where('merchant', '[0-9]+')
        ->name('merchant.customer.credentials.rotate');
    Route::post('/{merchant}/payment-requests', [CustomerMerchantController::class, 'createPaymentRequest'])
        ->where('merchant', '[0-9]+')
        ->middleware('throttle:merchant-payment-requests')
        ->name('merchant.customer.payment-requests');
});

Route::middleware('auth.merchant')->group(function () {
    Route::post('/payment-requests', [MerchantController::class, 'createPaymentRequest'])
        ->middleware('throttle:merchant-payment-requests')
        ->name('merchant.payment-requests');
    Route::post('/terminal/connection-token', [StripeController::class, 'terminalConnectionToken'])
        ->middleware('throttle:merchant-terminal-token');
    Route::post('/terminal/create-intent', [StripeController::class, 'createTerminal'])
        ->middleware(['idempotency', 'throttle:merchant-terminal-intent']);
});
