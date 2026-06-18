<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Ponto central de registro dos domínios do Monólito Modular.
 *
 * Cada domínio em app/Domains/<Dominio> expõe o seu próprio ServiceProvider,
 * responsável por bindings, eventos, policies e rotas do domínio.
 * Adicione novos domínios ao array abaixo conforme forem criados.
 */
class DomainServiceProvider extends ServiceProvider
{
    /**
     * @var array<int, class-string<\Illuminate\Support\ServiceProvider>>
     */
    private array $domainProviders = [
        \App\Domains\Auth\AuthServiceProvider::class,
        \App\Domains\Onboarding\OnboardingServiceProvider::class,
        \App\Domains\Sms\SmsServiceProvider::class,
        \App\Domains\Kyc\KycServiceProvider::class,
        \App\Domains\Ebury\EburyServiceProvider::class,
        \App\Domains\Stripe\StripeServiceProvider::class,
        \App\Domains\Wallester\WallesterServiceProvider::class,
        \App\Domains\Backoffice\BackofficeServiceProvider::class,
        \App\Domains\Wallet\WalletServiceProvider::class,
        \App\Domains\Merchant\MerchantServiceProvider::class,
        \App\Domains\Customer\CustomerServiceProvider::class,
        \App\Domains\Settings\SettingsServiceProvider::class,
        \App\Domains\Tpa\TpaServiceProvider::class,
    ];

    public function register(): void
    {
        foreach ($this->domainProviders as $provider) {
            $this->app->register($provider);
        }
    }
}
