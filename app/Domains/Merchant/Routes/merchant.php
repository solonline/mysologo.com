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
    Route::post('/register', [CustomerMerchantController::class, 'register'])
        ->name('merchant.register');
});

Route::middleware(['auth.merchant', 'throttle:stripe-intent'])->group(function () {
    Route::post('/payment-requests', [MerchantController::class, 'createPaymentRequest'])
        ->name('merchant.payment-requests');
    Route::post('/terminal/connection-token', [StripeController::class, 'terminalConnectionToken']);
    Route::post('/terminal/create-intent', [StripeController::class, 'createTerminal'])
        ->middleware('idempotency');
});
