<?php

use App\Domains\Auth\Controllers\PasswordResetPageController;
use Illuminate\Support\Facades\Route;

Route::get('/password/reset', [PasswordResetPageController::class, 'show'])
    ->name('password.reset');

Route::post('/password/reset', [PasswordResetPageController::class, 'update'])
    ->middleware('throttle:6,1')
    ->name('password.update');

Route::get('/password/reset/success', [PasswordResetPageController::class, 'success'])
    ->name('password.reset.success');
