<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/create-didit-session', [AuthController::class, 'createDiditSession']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [UserController::class, 'me']);

    Route::group(['prefix' => 'didit'], function () {
      Route::post('/session', [AuthController::class, 'createDiditSession']);
    //   Route::get('/session/{sessionId}', [AuthController::class, 'getDiditSessionStatus']);
    });
});
