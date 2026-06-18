<?php

use App\Domains\Wallet\Controllers\WalletAccountController;
use App\Domains\Wallet\Controllers\WalletDashboardController;
use App\Domains\Wallet\Controllers\WalletTransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'domain' => 'wallet',
    'status' => 'ok',
]))->name('wallet.v2.health');

Route::middleware([
    'auth:sanctum',
    'customer.active',
    'customer.email.verified',
    'customer.document.approved',
])->group(function () {
    Route::get('/dashboard', [WalletDashboardController::class, 'summary'])->name('wallet.v2.dashboard');
    Route::get('/dashboard/summary', [WalletDashboardController::class, 'summary'])->name('wallet.v2.dashboard.summary');

    Route::prefix('account')->group(function () {
        Route::get('/', [WalletAccountController::class, 'index'])->name('wallet.v2.account');
        Route::get('/balance', [WalletAccountController::class, 'balance'])->name('wallet.v2.account.balance');
    });

    Route::prefix('transactions')->group(function () {
        Route::get('/', [WalletTransactionController::class, 'index'])->name('wallet.v2.transactions.index');
        Route::get('/{id}', [WalletTransactionController::class, 'show'])->where('id', '[0-9]+')->name('wallet.v2.transactions.show');
        Route::post('/p2p', [WalletTransactionController::class, 'p2p'])->name('wallet.v2.transactions.p2p');
    });
});
