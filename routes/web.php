<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\EmailValidationController;

Route::get('/', function () {
    return view('welcome');
});

// Rotas de verificação de e-mail
Route::get('email/verify', function () {
    return view('auth.verify-email');
})->middleware('auth')->name('verification.notice');

// Rota de verificação de e-mail assinada
Route::get('email/verify/{id}/{hash}', [EmailValidationController::class, 'show'])
    ->middleware(['signed'])
    ->name('verification.verify');


Route::get('email/validated', [EmailValidationController::class, 'show'])->name('email.validated');

Route::post('email/verification-notification', function () {
    request()->user()->sendEmailVerificationNotification();
    return back()->with('message', 'Verification link sent!');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');
