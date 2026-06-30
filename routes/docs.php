<?php

use App\Http\Controllers\DocsController;
use Illuminate\Support\Facades\Route;

Route::prefix('docs')->group(function () {
    Route::get('/openapi/{path}', [DocsController::class, 'spec'])
        ->where('path', '.*')
        ->name('docs.openapi.file');

    Route::get('/partners', [DocsController::class, 'partners'])->name('docs.partners');
    Route::get('/partners/guides', [DocsController::class, 'guidesPartners'])->name('docs.guides.partners');
    Route::get('/partners/guides/{slug}', [DocsController::class, 'guidePartners'])
        ->where('slug', '[a-z0-9\-]+')
        ->name('docs.guides.partners.show');

    Route::middleware('docs.internal')->group(function () {
        Route::get('/', [DocsController::class, 'internal'])->name('docs.internal');
        Route::get('/guides', [DocsController::class, 'guidesInternal'])->name('docs.guides.internal');
        Route::get('/guides/{slug}', [DocsController::class, 'guideInternal'])
            ->where('slug', '[a-z0-9\-]+')
            ->name('docs.guides.internal.show');
    });
});
