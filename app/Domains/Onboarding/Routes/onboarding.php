<?php

use App\Domains\Onboarding\Controllers\PhoneVerificationController;
use Illuminate\Support\Facades\Route;

/*
| Rotas do domínio Onboarding — /api/onboarding/*
*/

Route::get('/health', fn () => response()->json([
    'domain' => 'onboarding',
    'status' => 'ok',
]))->name('onboarding.health');

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('phone')->group(function () {
        Route::post('/send', [PhoneVerificationController::class, 'send'])->name('onboarding.phone.send');
        Route::post('/validate', [PhoneVerificationController::class, 'validateCode'])->name('onboarding.phone.validate');
    });
});
