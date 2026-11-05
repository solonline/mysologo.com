<?php

use App\Domains\Merchant\Controllers\MerchantController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'domain' => 'merchant',
    'status' => 'ok',
]))->name('merchant.health');

Route::middleware('auth.merchant')->group(function () {
    Route::post('/payment-requests', [MerchantController::class, 'createPaymentRequest'])
        ->middleware('throttle:merchant-payment-requests')
        ->name('merchant.payment-requests');
});
