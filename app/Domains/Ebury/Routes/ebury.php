<?php

use App\Domains\Ebury\Controllers\EburyWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/ebury', [EburyWebhookController::class, 'handle'])
    ->name('ebury.v2.webhook');

Route::get('/health', fn () => response()->json([
    'domain' => 'ebury',
    'status' => 'ok',
]))->name('ebury.v2.health');
