<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Webhooks\DiditWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/create-didit-session', [AuthController::class, 'createDiditSession']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/webhooks/didit', [DiditWebhookController::class, 'handle'])->name('didit.webhook');

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [UserController::class, 'me']);
    Route::post('/create-pin', [\App\Http\Controllers\Api\Auth\PinController::class, 'store']);
});
