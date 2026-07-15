<?php

use App\Domains\Stripe\Controllers\StripeController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'domain' => 'stripe',
    'status' => 'ok',
]))->name('stripe.health');

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
        Route::get('/payment-intents/{paymentIntent}', [StripeController::class, 'showPaymentIntent'])
            ->where('paymentIntent', '[0-9]+');
        Route::post('/create-funding', [StripeController::class, 'createFunding'])
            ->middleware('throttle:stripe-intent');
        Route::get('/get-funding-instructions', [StripeController::class, 'getFundingInstructions']);
        Route::post('/terminal/{paymentIntent}/capture', [StripeController::class, 'captureTerminal'])
            ->where('paymentIntent', '[0-9]+')
            ->middleware('throttle:stripe-intent');
    });
});
