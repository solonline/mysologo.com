#!/usr/bin/env php
<?php

use App\Domains\Wallester\Clients\WallesterHttpClient;
use Illuminate\Contracts\Console\Kernel;

/**
 * Gera um JWT Wallester (RS256) para colar em Postman → Environment → wallester_jwt.
 *
 * Uso:
 *   php postman/scripts/wallester-jwt.php
 *   php postman/scripts/wallester-jwt.php | clip   (Windows)
 */

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo app(WallesterHttpClient::class)->authenticate();
