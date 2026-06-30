<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'domain' => 'ebury',
    'status' => 'ok',
]))->name('ebury.health');
