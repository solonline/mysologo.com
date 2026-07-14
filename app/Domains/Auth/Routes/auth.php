<?php

use App\Domains\Auth\Controllers\AuthController;
use App\Domains\Auth\Controllers\PinController;
use Illuminate\Support\Facades\Route;

/*
| Rotas do domínio Auth — /api/auth/*
| Verificação de telefone: /api/onboarding/phone/*
*/

Route::get('/health', fn () => response()->json([
    'domain' => 'auth',
    'status' => 'ok',
]))->name('auth.health');

Route::post('/register-retail', [AuthController::class, 'registerRetail'])->name('auth.register-retail');
Route::post('/register-corporate', [AuthController::class, 'registerCorporate'])->name('auth.register-corporate');
Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
Route::post('/login-pin', [AuthController::class, 'loginWithPin'])->name('auth.login-pin');
Route::post('/login-tpa', [AuthController::class, 'loginWithTpa'])->name('auth.login-tpa');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot-password');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset-password');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('auth.logout');

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware([
        'customer.active',
        'customer.email.verified',
        'customer.document.approved',
    ])->group(function () {
        Route::post('/create-pin', [PinController::class, 'store'])->name('auth.create-pin');
        Route::post('/reset-pin', [PinController::class, 'reset'])->name('auth.reset-pin');
    });
});
