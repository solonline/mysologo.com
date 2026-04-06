<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Webhooks\DiditWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/register-retail', [AuthController::class, 'registerRetail']);
Route::post('/register-corporate', [AuthController::class, 'registerCorporate']);
Route::post('/create-didit-session', [AuthController::class, 'createDiditSession']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/webhooks/didit', [DiditWebhookController::class, 'handle'])->name('didit.webhook');

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::group(['prefix' => 'customer'], function () {
        Route::get('/me', [\App\Http\Controllers\Api\CustomerController::class, 'me']);
        Route::post('/update', [\App\Http\Controllers\Api\CustomerController::class, 'update']);
        Route::post('/create-corporate-responsible', [\App\Http\Controllers\Api\CustomerController::class, 'createResponsible']);
        Route::post('/submit-corporate-documents', [\App\Http\Controllers\Api\CustomerController::class, 'submitCorporateDocuments']);
        Route::post('/send-validation-email', [\App\Http\Controllers\Api\CustomerController::class, 'sendValidationEmail']);
    });
});
