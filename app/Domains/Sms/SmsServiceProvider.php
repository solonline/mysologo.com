<?php

namespace App\Domains\Sms;

use App\Domains\Sms\Clients\SmsHttpClient;
use App\Domains\Sms\Contracts\SmsGatewayInterface;
use App\Domains\Sms\Gateways\SmsGateway;
use Illuminate\Support\ServiceProvider;

class SmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmsHttpClient::class);
        $this->app->bind(SmsGatewayInterface::class, SmsGateway::class);
    }
}
