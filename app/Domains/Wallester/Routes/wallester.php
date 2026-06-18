<?php

use App\Domains\Wallester\Controllers\WallesterWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/wallester', [WallesterWebhookController::class, 'handle'])
    ->name('wallester.v2.webhook');

Route::get('/health', fn () => response()->json([
    'domain' => 'wallester',
    'status' => 'ok',
]))->name('wallester.v2.health');
