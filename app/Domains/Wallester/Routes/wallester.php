<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'domain' => 'wallester',
    'status' => 'ok',
]))->name('wallester.health');
