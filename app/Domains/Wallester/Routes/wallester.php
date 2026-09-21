<?php

use App\Domains\Wallester\Controllers\Wallester3dsChallengeController;
use App\Domains\Wallester\Controllers\WallesterAccountController;
use App\Domains\Wallester\Controllers\WallesterCardController;
use App\Domains\Wallester\Controllers\WallesterTransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'domain' => 'wallester',
    'status' => 'ok',
]))->name('wallester.health');

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware([
        'customer.active',
        'customer.email.verified',
        'customer.document.approved',
    ])->group(function () {
        Route::get('/accounts', [WallesterAccountController::class, 'index'])
            ->name('wallester.accounts.index');
        Route::get('/accounts/{account}', [WallesterAccountController::class, 'show'])
            ->where('account', '[0-9a-fA-F-]{36}')
            ->name('wallester.accounts.show');
        Route::get('/cards', [WallesterCardController::class, 'index'])
            ->name('wallester.cards.index');
        Route::get('/cards/issuance-quote', [WallesterCardController::class, 'issuanceQuote'])
            ->name('wallester.cards.issuance-quote');
        Route::post('/cards', [WallesterCardController::class, 'store'])
            ->name('wallester.cards.store');
        Route::get('/cards/{card}', [WallesterCardController::class, 'show'])
            ->name('wallester.cards.show');
        Route::post('/cards/{card}/sensitive', [WallesterCardController::class, 'sensitive'])
            ->name('wallester.cards.sensitive');
        Route::post('/cards/{card}/encrypted-pin', [WallesterCardController::class, 'encryptedPin'])
            ->name('wallester.cards.encrypted-pin');
        Route::post('/cards/{card}/activate', [WallesterCardController::class, 'activate'])
            ->name('wallester.cards.activate');
        Route::post('/cards/{card}/activate-by-cvv', [WallesterCardController::class, 'activateByCVV'])
            ->name('wallester.cards.activate-by-cvv');
        Route::post('/cards/{card}/block', [WallesterCardController::class, 'block'])
            ->name('wallester.cards.block');
        Route::post('/cards/{card}/unblock', [WallesterCardController::class, 'unblock'])
            ->name('wallester.cards.unblock');
        Route::post('/cards/{card}/close', [WallesterCardController::class, 'close'])
            ->name('wallester.cards.close');
    });

    Route::middleware([
        'customer.active',
        'customer.email.verified',
        'customer.document.approved',
    ])->prefix('3ds-challenges')->group(function () {
        Route::get('/pending', [Wallester3dsChallengeController::class, 'pending'])
            ->name('wallester.3ds-challenges.pending');
        Route::post('/{challenge}/approve', [Wallester3dsChallengeController::class, 'approve'])
            ->name('wallester.3ds-challenges.approve');
        Route::post('/{challenge}/reject', [Wallester3dsChallengeController::class, 'reject'])
            ->name('wallester.3ds-challenges.reject');
    });

    Route::middleware([
        'customer.active',
        'customer.email.verified',
        'customer.document.approved',
    ])->prefix('transactions')->group(function () {
        Route::get('/', [WallesterTransactionController::class, 'index'])
            ->name('wallester.transactions.index');
        Route::get('/{transaction}', [WallesterTransactionController::class, 'show'])
            ->where('transaction', '[0-9a-fA-F-]{36}')
            ->name('wallester.transactions.show');
    });
});
