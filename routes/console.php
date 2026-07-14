<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('stripe:settle-balances')->dailyAt('01:00');
Schedule::command('wallester:sync-card-movements')->twiceDaily(0, 12);
