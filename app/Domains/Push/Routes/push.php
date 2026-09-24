<?php

use App\Domains\Push\Controllers\PushDeviceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::put('/devices', [PushDeviceController::class, 'update'])->name('push.devices.update');
    Route::delete('/devices', [PushDeviceController::class, 'destroy'])->name('push.devices.destroy');
});
