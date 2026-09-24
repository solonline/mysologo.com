<?php

namespace App\Domains\Whatsapp;

use App\Domains\Whatsapp\Clients\EvolutionHttpClient;
use App\Domains\Whatsapp\Contracts\WhatsappClientInterface;
use App\Domains\Whatsapp\Contracts\WhatsappGatewayInterface;
use App\Domains\Whatsapp\Gateways\WhatsappGateway;
use App\Domains\Whatsapp\Services\WhatsappService;
use App\Domains\Whatsapp\Services\WhatsappSettingsService;
use Illuminate\Support\ServiceProvider;

class WhatsappServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WhatsappSettingsService::class);
        $this->app->bind(WhatsappClientInterface::class, EvolutionHttpClient::class);
        $this->app->bind(WhatsappGatewayInterface::class, WhatsappGateway::class);
        $this->app->singleton(WhatsappService::class);
    }
}
