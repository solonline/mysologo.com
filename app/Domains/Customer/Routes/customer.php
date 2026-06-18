<?php

use App\Domains\Customer\Controllers\CustomerController;
use App\Domains\Customer\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'domain' => 'customer',
    'status' => 'ok',
]))->name('customer.v2.health');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [CustomerController::class, 'me'])->name('customer.v2.me');
    Route::post('/update', [CustomerController::class, 'update'])->name('customer.v2.update');
    Route::post('/create-corporate-responsible', [CustomerController::class, 'createResponsible'])->name('customer.v2.create-responsible');
    Route::post('/submit-corporate-documents', [CustomerController::class, 'submitCorporateDocuments'])->name('customer.v2.submit-corporate-documents');
    Route::post('/send-validation-email', [CustomerController::class, 'sendValidationEmail'])->name('customer.v2.send-validation-email');
    Route::post('/upload-avatar', [CustomerController::class, 'uploadAvatar'])->name('customer.v2.upload-avatar');
    Route::get('/find/{username}', [CustomerController::class, 'findByUsername'])
        ->where('username', '[a-zA-Z0-9_]+')
        ->name('customer.v2.find-by-username');

    Route::middleware([
        'customer.active',
        'customer.email.verified',
        'customer.document.approved',
    ])->prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('customer.v2.notifications.index');
        Route::post('/{notification}/read', [NotificationController::class, 'markRead'])->name('customer.v2.notifications.read');
        Route::post('/read-all', [NotificationController::class, 'markAllRead'])->name('customer.v2.notifications.read-all');
    });
});
