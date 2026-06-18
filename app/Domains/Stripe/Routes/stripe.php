<?php

use App\Domains\Stripe\Controllers\StripeController;
use App\Domains\Stripe\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])->name('stripe.v2.webhook');

Route::middleware(['auth.merchant', 'throttle:stripe-intent'])->prefix('merchant')->group(function () {
    Route::post('/terminal/connection-token', [StripeController::class, 'terminalConnectionToken']);
    Route::post('/terminal/create-intent', [StripeController::class, 'createTerminal'])->middleware('idempotency');
});

Route::middleware('auth:sanctum')->group(function () {
    $customerEligibilityMiddlewares = [
        'customer.active',
        'customer.email.verified',
        'customer.document.approved',
    ];

    Route::middleware($customerEligibilityMiddlewares)->group(function () {
        Route::get('/payment-methods', [StripeController::class, 'paymentMethods']);
        Route::get('/fee-estimates', [StripeController::class, 'feeEstimates']);
        Route::post('/create-intent-payment', [StripeController::class, 'create'])
            ->middleware(['idempotency', 'throttle:stripe-intent']);
        Route::post('/terminal/{transaction}/capture', [StripeController::class, 'captureTerminal'])
            ->where('transaction', '[0-9]+')
            ->middleware('throttle:stripe-intent');
    });
});

Route::get('/health', fn () => response()->json([
    'domain' => 'stripe',
    'status' => 'ok',
]))->name('stripe.v2.health');
