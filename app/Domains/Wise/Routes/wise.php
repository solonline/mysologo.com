<?php

use App\Domains\Wise\Controllers\WiseBalanceController;
use App\Domains\Wise\Controllers\WiseBeneficiaryController;
use App\Domains\Wise\Controllers\WiseQuoteController;
use App\Domains\Wise\Controllers\WiseTransferController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'domain' => 'wise',
    'status' => 'ok',
]))->name('wise.health');

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware([
        'customer.active',
        'customer.email.verified',
        'customer.document.approved',
    ])->group(function () {
        Route::get('/balances', [WiseBalanceController::class, 'index'])
            ->name('wise.balances.index');
        Route::get('/beneficiaries', [WiseBeneficiaryController::class, 'index'])
            ->name('wise.beneficiaries.index');
        Route::post('/beneficiaries', [WiseBeneficiaryController::class, 'store'])
            ->name('wise.beneficiaries.store');
        Route::post('/quotes', [WiseQuoteController::class, 'store'])
            ->name('wise.quotes.store');
        Route::post('/transfers', [WiseTransferController::class, 'store'])
            ->name('wise.transfers.store');
    });
});
