<?php

use App\Domains\Ebury\Controllers\EburyWebhookController;
use App\Domains\Kyc\Controllers\DiditWebhookController;
use App\Domains\Stripe\Controllers\StripeWebhookController;
use App\Domains\Wallester\Controllers\Wallester3dsWebhookController;
use App\Domains\Wallester\Controllers\WallesterWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Webhooks com URLs estáveis para integrações externas.
| Demais rotas: app/Domains/{dominio}/Routes/ (prefixo /api/{dominio}).
*/

Route::post('/webhooks/didit', [DiditWebhookController::class, 'handle'])->name('didit.webhook');
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');
Route::post('/webhooks/wallester', [WallesterWebhookController::class, 'handle'])->name('wallester.webhook');

Route::middleware('wallester.3ds.webhook')->prefix('webhooks/wallester/3ds')->group(function () {
    Route::post('/initiate-action-event', [Wallester3dsWebhookController::class, 'initiateAction'])
        ->name('wallester.3ds.initiate');
    Route::post('/validate-event', [Wallester3dsWebhookController::class, 'validate'])
        ->name('wallester.3ds.validate');
});
Route::post('/webhooks/ebury', [EburyWebhookController::class, 'handle'])->name('ebury.webhook');
