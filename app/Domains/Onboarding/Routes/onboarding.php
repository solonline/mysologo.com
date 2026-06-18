<?php

use App\Domains\Onboarding\Controllers\PhoneVerificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas do domínio Onboarding (v2 — fase de testes)
|--------------------------------------------------------------------------
| Montadas em /api/v2/onboarding via OnboardingServiceProvider.
| As rotas legadas em routes/api.php permanecem inalteradas até a troca final.
*/

Route::get('/health', function () {
    return response()->json([
        'domain' => 'onboarding',
        'version' => 'v2',
        'status' => 'ok',
    ]);
})->name('onboarding.v2.health');

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('phone')->group(function () {
        Route::post('/send', [PhoneVerificationController::class, 'send'])->name('onboarding.v2.phone.send');
        Route::post('/validate', [PhoneVerificationController::class, 'validateCode'])->name('onboarding.v2.phone.validate');
    });
});
