<?php

namespace Tests\Feature\Domains\Merchant;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Models\Merchant;
use App\Models\MerchantPaymentRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class MerchantPayPageTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_pay_page_uses_automatic_payment_methods_when_types_omitted(): void
    {
        $paymentRequest = $this->makePaymentRequest();

        $this->mock(\App\Domains\Stripe\Contracts\StripeGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('createPaymentIntentWithMode')
                ->once()
                ->withArgs(function (array $payload) {
                    return isset($payload['automatic_payment_methods']['enabled'])
                        && $payload['automatic_payment_methods']['enabled'] === true
                        && ! array_key_exists('payment_method_types', $payload);
                })
                ->andReturn([
                    'status' => 'success',
                    'data' => [
                        'id' => 'pi_test_auto',
                        'client_secret' => 'pi_test_auto_secret',
                    ],
                ]);
        });

        $this->mock(\App\Domains\Settings\Services\SettingsService::class, function ($mock) {
            $mock->shouldReceive('getByKey')->andReturn('pk_test_dummy');
        });

        $this->get(route('pay.show', $paymentRequest->uuid))
            ->assertOk()
            ->assertSee('Loja Pay');
    }

    public function test_pay_page_uses_explicit_payment_method_types_when_set(): void
    {
        $paymentRequest = $this->makePaymentRequest([
            'payment_method_types' => ['card', 'mbway'],
        ]);

        $this->mock(\App\Domains\Stripe\Contracts\StripeGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('createPaymentIntentWithMode')
                ->once()
                ->withArgs(function (array $payload) {
                    return ($payload['payment_method_types'] ?? null) === ['card', 'mbway']
                        && ! array_key_exists('automatic_payment_methods', $payload);
                })
                ->andReturn([
                    'status' => 'success',
                    'data' => [
                        'id' => 'pi_test_card',
                        'client_secret' => 'pi_test_card_secret',
                    ],
                ]);
        });

        $this->mock(\App\Domains\Settings\Services\SettingsService::class, function ($mock) {
            $mock->shouldReceive('getByKey')->andReturn('pk_test_dummy');
        });

        $this->get(route('pay.show', $paymentRequest->uuid))
            ->assertOk()
            ->assertSee('Loja Pay');
    }

    public function test_pay_page_persists_local_payment_intent_as_merchant_payment(): void
    {
        $paymentRequest = $this->makePaymentRequest();

        $this->mock(\App\Domains\Stripe\Contracts\StripeGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('createPaymentIntentWithMode')
                ->once()
                ->andReturn([
                    'status' => 'success',
                    'data' => [
                        'id' => 'pi_test_local_mp',
                        'client_secret' => 'pi_test_local_mp_secret',
                        'status' => 'requires_payment_method',
                        'amount' => 1500,
                        'currency' => 'eur',
                    ],
                ]);
        });

        $this->mock(\App\Domains\Settings\Services\SettingsService::class, function ($mock) {
            $mock->shouldReceive('getByKey')->andReturn('pk_test_dummy');
        });

        $this->get(route('pay.show', $paymentRequest->uuid))
            ->assertOk();

        $this->assertDatabaseHas('stripe_payment_intents', [
            'stripe_payment_intent_id' => 'pi_test_local_mp',
            'type' => \App\Domains\Wallet\Enums\TransactionType::MERCHANT_PAYMENT->value,
            'purpose' => 'merchant_payment',
        ]);
    }

    public function test_pay_page_shows_completed_message_when_already_paid(): void
    {
        $paymentRequest = $this->makePaymentRequest([
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $this->mock(\App\Domains\Settings\Services\SettingsService::class, function ($mock) {
            $mock->shouldReceive('getByKey')->andReturn('pk_test_dummy');
        });

        $this->get(route('pay.show', $paymentRequest->uuid))
            ->assertOk()
            ->assertSee('Pagamento concluído')
            ->assertSee('Obrigado. O pagamento foi registado com sucesso.');
    }

    /** @param array<string, mixed> $attributes */
    private function makePaymentRequest(array $attributes = []): MerchantPaymentRequest
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
            'currency' => 'EUR',
        ]);

        $merchant = Merchant::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Loja Pay',
            'slug' => 'loja-pay-'.uniqid(),
            'api_key' => hash('sha256', 'yoop_test_pay_'.uniqid()),
            'mode' => 'test',
            'is_active' => true,
        ]);

        return MerchantPaymentRequest::query()->create(array_merge([
            'merchant_id' => $merchant->id,
            'amount' => 1500,
            'currency' => 'EUR',
            'description' => 'Teste pay',
            'status' => 'pending',
            'expires_at' => now()->addHour(),
            'language' => 'pt',
        ], $attributes));
    }
}
