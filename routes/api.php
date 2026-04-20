<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\PinController;
use App\Http\Controllers\Webhooks\DiditWebhookController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;


Route::post('/register-retail', [AuthController::class, 'registerRetail']);
Route::post('/register-corporate', [AuthController::class, 'registerCorporate']);
Route::post('/create-didit-session', [AuthController::class, 'createDiditSession']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/login-pin', [AuthController::class, 'loginWithPin']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/webhooks/didit', [DiditWebhookController::class, 'handle'])->name('didit.webhook');
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/create-pin', [PinController::class, 'store']);

    Route::group(['prefix' => 'settings'], function () {
        Route::get('/', [\App\Http\Controllers\Api\SettingController::class, 'index']);
        Route::get('/{key}', [\App\Http\Controllers\Api\SettingController::class, 'get'])->where('key', '[a-z_]+');
        Route::post('/{key}', [\App\Http\Controllers\Api\SettingController::class, 'set'])->where('key', '[a-z_]+');
    });

    Route::group(['prefix' => 'dashboard'], function () {
        Route::get('/summary', [\App\Http\Controllers\Api\DashboardCustomerController::class, 'summary']);
    });

    Route::group(['prefix' => 'customer'], function () {
        Route::get('/me', [\App\Http\Controllers\Api\CustomerController::class, 'me']);
        Route::post('/update', [\App\Http\Controllers\Api\CustomerController::class, 'update']);
        Route::post('/create-corporate-responsible', [\App\Http\Controllers\Api\CustomerController::class, 'createResponsible']);
        Route::post('/submit-corporate-documents', [\App\Http\Controllers\Api\CustomerController::class, 'submitCorporateDocuments']);
        Route::post('/send-validation-email', [\App\Http\Controllers\Api\CustomerController::class, 'sendValidationEmail']);
    });

    Route::get('/dashboard', [\App\Http\Controllers\Api\DashboardCustomerController::class, 'summary']);

    Route::group(['prefix' => 'stripe'], function () {
        Route::get('/payment-methods', [\App\Http\Controllers\Api\StripeController::class, 'paymentMethods']);
        Route::post('/create-intent-payment', [\App\Http\Controllers\Api\StripeController::class, 'create']);
    });
});
