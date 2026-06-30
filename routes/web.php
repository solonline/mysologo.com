<?php

use App\Domains\Auth\Controllers\EmailValidationController;
use App\Domains\Merchant\Controllers\PayController;
use App\Domains\Stripe\Clients\StripeHttpClient;
use App\Domains\Wallester\Services\WallesterService;
use Illuminate\Support\Facades\Route;

/*
| Rotas web gerais da aplicação (não-backoffice).
| Staff login + dashboard: app/Domains/Backoffice/Routes/web.php
*/

Route::middleware('web')->group(base_path('app/Domains/Backoffice/Routes/web.php'));

// Rotas de verificação de e-mail (clientes)
Route::get('email/verify', function () {
    return view('auth.verify-email');
})->middleware('auth')->name('verification.notice');

Route::get('email/verify/{id}/{hash}', [EmailValidationController::class, 'verifyFrontend'])
    ->middleware(['signed'])
    ->name('verification.verify');

Route::get('/pay/{uuid}', [PayController::class, 'show'])
    ->where('uuid', '[0-9a-f\-]{36}')
    ->name('pay.show');

Route::post('email/verification-notification', function () {
    request()->user()->sendEmailVerificationNotification();

    return back()->with('message', 'Verification link sent!');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');

Route::middleware('web')->group(base_path('routes/docs.php'));

// Rotas de desenvolvimento / testes manuais
Route::get('/test-wallester-account', function (WallesterService $wallesterService) {
    $data = ['name' => 'Daniel Silva'];

    try {
        return response()->json($wallesterService->createAccount($data));
    } catch (Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
});

Route::get('/test/stripe-funding-instructions', function (StripeHttpClient $stripeClient) {
    $data = [
        'funding_type' => 'bank_transfer',
        'bank_transfer' => ['type' => 'us_bank_transfer'],
        'currency' => 'usd',
    ];

    try {
        return response()->json($stripeClient->createCustomerFundingInstructions('cus_USJQLXo86JyvnJ', $data));
    } catch (Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
});
