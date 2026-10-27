<?php

namespace App\Providers;

use App\Domains\Auth\AuthServiceProvider;
use App\Domains\Backoffice\BackofficeServiceProvider;
use App\Domains\Customer\CustomerServiceProvider;
use App\Domains\Documents\DocumentsServiceProvider;
use App\Domains\Ebury\EburyServiceProvider;
use App\Domains\Fx\FxServiceProvider;
use App\Domains\Kyc\KycServiceProvider;
use App\Domains\Merchant\MerchantServiceProvider;
use App\Domains\Onboarding\OnboardingServiceProvider;
use App\Domains\Push\PushServiceProvider;
use App\Domains\Reward\RewardServiceProvider;
use App\Domains\Settings\SettingsServiceProvider;
use App\Domains\Sms\SmsServiceProvider;
use App\Domains\Stripe\StripeServiceProvider;
use App\Domains\Tpa\TpaServiceProvider;
use App\Domains\Wallester\WallesterServiceProvider;
use App\Domains\Wallet\WalletServiceProvider;
use App\Domains\Whatsapp\WhatsappServiceProvider;
use App\Domains\Wise\WiseServiceProvider;
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
     * @var array<int, class-string<ServiceProvider>>
     */
    private array $domainProviders = [
        AuthServiceProvider::class,
        OnboardingServiceProvider::class,
        SmsServiceProvider::class,
        KycServiceProvider::class,
        EburyServiceProvider::class,
        StripeServiceProvider::class,
        WallesterServiceProvider::class,
        WiseServiceProvider::class,
        FxServiceProvider::class,
        BackofficeServiceProvider::class,
        WalletServiceProvider::class,
        MerchantServiceProvider::class,
        CustomerServiceProvider::class,
        PushServiceProvider::class,
        SettingsServiceProvider::class,
        TpaServiceProvider::class,
        WhatsappServiceProvider::class,
        DocumentsServiceProvider::class,
        RewardServiceProvider::class,
    ];

    public function register(): void
    {
        foreach ($this->domainProviders as $provider) {
            $this->app->register($provider);
        }
    }
}
