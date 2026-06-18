<?php

use Illuminate\Support\Facades\Route;

/*
| Rotas API v2 do domínio Backoffice.
| Rotas web (staff): app/Domains/Backoffice/Routes/web.php (via routes/web.php).
*/

Route::get('/health', fn () => response()->json([
    'domain' => 'backoffice',
    'status' => 'ok',
]))->name('backoffice.v2.health');
