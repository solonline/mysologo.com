<?php

use App\Domains\Wallet\Controllers\WalletAccountController;
use App\Domains\Wallet\Controllers\WalletDashboardController;
use App\Domains\Wallet\Controllers\WalletFundingAccountController;
use App\Domains\Wallet\Controllers\WalletTransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'domain' => 'wallet',
    'status' => 'ok',
]))->name('wallet.health');

Route::middleware([
    'auth:sanctum',
    'customer.active',
    'customer.email.verified',
    'customer.document.approved',
])->group(function () {
    Route::get('/dashboard', [WalletDashboardController::class, 'summary'])->name('wallet.dashboard');
    Route::get('/dashboard/summary', [WalletDashboardController::class, 'summary'])->name('wallet.dashboard.summary');

    Route::prefix('account')->group(function () {
        Route::get('/', [WalletAccountController::class, 'index'])->name('wallet.account');
        Route::get('/balance', [WalletAccountController::class, 'balance'])->name('wallet.account.balance');
    });

    Route::get('/funding-accounts/{id}/movements', [WalletFundingAccountController::class, 'movements'])
        ->where('id', '[0-9]+')
        ->name('wallet.funding-accounts.movements');

    Route::prefix('transactions')->group(function () {
        Route::get('/', [WalletTransactionController::class, 'index'])->name('wallet.transactions.index');
        Route::get('/{id}', [WalletTransactionController::class, 'show'])->where('id', '[0-9]+')->name('wallet.transactions.show');
        Route::post('/p2p', [WalletTransactionController::class, 'p2p'])->name('wallet.transactions.p2p');
    });
});
