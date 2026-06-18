<?php

use App\Domains\Merchant\Controllers\CustomerMerchantController;
use App\Domains\Merchant\Controllers\MerchantController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'domain' => 'merchant',
    'status' => 'ok',
]))->name('merchant.v2.health');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/register', [CustomerMerchantController::class, 'register'])
        ->name('merchant.v2.register');
});

Route::middleware(['auth.merchant', 'throttle:stripe-intent'])->group(function () {
    Route::post('/payment-requests', [MerchantController::class, 'createPaymentRequest'])
        ->name('merchant.v2.payment-requests');
});
