<?php

use App\Domains\Tpa\Controllers\TpaController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'domain' => 'tpa',
    'status' => 'ok',
]))->name('tpa.v2.health');

Route::post('/link', [TpaController::class, 'link'])->name('tpa.v2.link');
