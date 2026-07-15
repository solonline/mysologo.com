<?php

use App\Domains\Customer\Controllers\CustomerController;
use App\Domains\Customer\Controllers\CustomerPhoneController;
use App\Domains\Customer\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'domain' => 'customer',
    'status' => 'ok',
]))->name('customer.health');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [CustomerController::class, 'me'])->name('customer.me');
    Route::post('/update', [CustomerController::class, 'update'])->name('customer.update');
    Route::post('/phone', [CustomerPhoneController::class, 'requestChange'])->name('customer.phone.request');
    Route::post('/phone/confirm', [CustomerPhoneController::class, 'confirmChange'])->name('customer.phone.confirm');
    Route::post('/create-corporate-responsible', [CustomerController::class, 'createResponsible'])->name('customer.create-responsible');
    Route::post('/send-validation-email', [CustomerController::class, 'sendValidationEmail'])->name('customer.send-validation-email');
    Route::post('/upload-avatar', [CustomerController::class, 'uploadAvatar'])->name('customer.upload-avatar');
    Route::get('/find/{username}', [CustomerController::class, 'findByUsername'])
        ->where('username', '[a-zA-Z0-9_]+')
        ->name('customer.find-by-username');

    Route::middleware([
        'customer.active',
        'customer.email.verified',
        'customer.document.approved',
    ])->prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('customer.notifications.index');
        Route::post('/{notification}/read', [NotificationController::class, 'markRead'])->name('customer.notifications.read');
        Route::post('/read-all', [NotificationController::class, 'markAllRead'])->name('customer.notifications.read-all');
    });
});
