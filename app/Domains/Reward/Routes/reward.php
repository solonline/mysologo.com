<?php

use App\Domains\Reward\Controllers\RewardController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/my-rewards', [RewardController::class, 'index'])->name('reward.my-rewards');
});
