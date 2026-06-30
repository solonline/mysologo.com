<?php

use App\Domains\Settings\Controllers\SettingController;
use App\Domains\Settings\Controllers\SystemInfoController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'domain' => 'settings',
    'status' => 'ok',
]))->name('settings.health');

Route::get('/system-info', [SystemInfoController::class, 'get'])->name('settings.system-info');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/', [SettingController::class, 'index'])->name('settings.index');
    Route::get('/{key}', [SettingController::class, 'get'])->where('key', '[a-z_.]+')->name('settings.get');
    Route::post('/{key}', [SettingController::class, 'set'])->where('key', '[a-z_.]+')->name('settings.set');
});
