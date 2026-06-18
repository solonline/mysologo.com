<?php

use App\Domains\Kyc\Controllers\DiditWebhookController;
use App\Domains\Kyc\Controllers\IdentityVerificationController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/didit', [DiditWebhookController::class, 'handle'])->name('kyc.v2.didit.webhook');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/identity/session', [IdentityVerificationController::class, 'createSession'])
        ->name('kyc.v2.identity.session');
});

Route::get('/health', fn () => response()->json([
    'domain' => 'kyc',
    'status' => 'ok',
]))->name('kyc.v2.health');
