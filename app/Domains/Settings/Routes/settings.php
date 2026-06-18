<?php

use App\Domains\Settings\Controllers\SettingController;
use App\Domains\Settings\Controllers\SystemInfoController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'domain' => 'settings',
    'status' => 'ok',
]))->name('settings.v2.health');

Route::get('/system-info', [SystemInfoController::class, 'get'])->name('settings.v2.system-info');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/', [SettingController::class, 'index'])->name('settings.v2.index');
    Route::get('/{key}', [SettingController::class, 'get'])->where('key', '[a-z_.]+')->name('settings.v2.get');
    Route::post('/{key}', [SettingController::class, 'set'])->where('key', '[a-z_.]+')->name('settings.v2.set');
});
