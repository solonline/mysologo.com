<?php

use App\Domains\Ebury\Controllers\EburyWebhookController;
use App\Domains\Kyc\Controllers\DiditWebhookController;
use App\Domains\Stripe\Controllers\StripeWebhookController;
use App\Domains\Wallester\Controllers\WallesterWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Webhooks com URLs estáveis para integrações externas.
| Demais rotas: app/Domains/{dominio}/Routes/ (prefixo /api/{dominio}).
*/

Route::post('/webhooks/didit', [DiditWebhookController::class, 'handle'])->name('didit.webhook');
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');
Route::post('/webhooks/wallester', [WallesterWebhookController::class, 'handle'])->name('wallester.webhook');
Route::post('/webhooks/ebury', [EburyWebhookController::class, 'handle'])->name('ebury.webhook');
