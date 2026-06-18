<?php

use App\Domains\Auth\Controllers\AuthController;
use App\Domains\Auth\Controllers\PinController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas do domínio Auth (v2 — fase de testes)
|--------------------------------------------------------------------------
| Montadas em /api/v2/auth via AuthServiceProvider.
| Verificação de telefone: /api/v2/onboarding/phone/* (domínio Onboarding).
*/

Route::get('/health', function () {
    return response()->json([
        'domain' => 'auth',
        'version' => 'v2',
        'status' => 'ok',
    ]);
})->name('auth.v2.health');

Route::post('/register-retail', [AuthController::class, 'registerRetail'])->name('auth.v2.register-retail');
Route::post('/register-corporate', [AuthController::class, 'registerCorporate'])->name('auth.v2.register-corporate');
Route::post('/login', [AuthController::class, 'login'])->name('auth.v2.login');
Route::post('/login-pin', [AuthController::class, 'loginWithPin'])->name('auth.v2.login-pin');
Route::post('/login-tpa', [AuthController::class, 'loginWithTpa'])->name('auth.v2.login-tpa');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.v2.forgot-password');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('auth.v2.reset-password');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('auth.v2.logout');

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware([
        'customer.active',
        'customer.email.verified',
        'customer.document.approved',
    ])->group(function () {
        Route::post('/create-pin', [PinController::class, 'store'])->name('auth.v2.create-pin');
    });
});
