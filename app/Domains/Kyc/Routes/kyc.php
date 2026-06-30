<?php

use App\Domains\Kyc\Controllers\IdentityVerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'domain' => 'kyc',
    'status' => 'ok',
]))->name('kyc.health');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/identity/session', [IdentityVerificationController::class, 'createSession'])
        ->name('kyc.identity.session');
});
