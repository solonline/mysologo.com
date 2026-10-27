<?php

namespace Tests\Feature\Domains\Merchant;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Settings\Services\SettingsService;
use App\Domains\Stripe\Contracts\StripeGatewayInterface;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\Merchant;
use App\Models\MerchantPaymentRequest;
use App\Models\Setting;
use App\Models\StripePaymentIntent;
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

        $this->mock(StripeGatewayInterface::class, function ($mock) {
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

        $this->mock(SettingsService::class, function ($mock) {
            $mock->shouldReceive('getByKey')->andReturn('pk_test_dummy');
        });

        $this->get(route('pay.show', $paymentRequest->uuid))
            ->assertOk()
            ->assertSee('Loja Pay');
    }

    public function test_pay_page_uses_explicit_payment_method_types_when_set(): void
    {
        $paymentRequest = $this->makePaymentRequest([
            'payment_method_types' => ['card', 'mb_way'],
        ]);

        $this->mock(StripeGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('createPaymentIntentWithMode')
                ->once()
                ->withArgs(function (array $payload) {
                    return ($payload['payment_method_types'] ?? null) === ['card', 'mb_way']
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

        $this->mock(SettingsService::class, function ($mock) {
            $mock->shouldReceive('getByKey')->andReturn('pk_test_dummy');
        });

        $this->get(route('pay.show', $paymentRequest->uuid))
            ->assertOk()
            ->assertSee('Loja Pay');
    }

    public function test_pay_page_persists_local_payment_intent_as_merchant_payment(): void
    {
        $paymentRequest = $this->makePaymentRequest();

        $this->mock(StripeGatewayInterface::class, function ($mock) {
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

        $this->mock(SettingsService::class, function ($mock) {
            $mock->shouldReceive('getByKey')->andReturn('pk_test_dummy');
        });

        $this->get(route('pay.show', $paymentRequest->uuid))
            ->assertOk();

        $this->assertDatabaseHas('stripe_payment_intents', [
            'stripe_payment_intent_id' => 'pi_test_local_mp',
            'type' => TransactionType::MERCHANT_PAYMENT->value,
            'purpose' => 'merchant_payment',
        ]);
    }

    public function test_pay_page_shows_completed_message_when_already_paid(): void
    {
        $paymentRequest = $this->makePaymentRequest([
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $this->mock(SettingsService::class, function ($mock) {
            $mock->shouldReceive('getByKey')->andReturn('pk_test_dummy');
        });

        $this->get(route('pay.show', $paymentRequest->uuid))
            ->assertOk()
            ->assertSee('Pagamento concluído')
            ->assertSee('Obrigado. O pagamento foi registado com sucesso.')
            ->assertSee('payment_request.completed', false)
            ->assertSee('YoopPay', false);
    }

    public function test_pay_page_embed_mode_exposes_post_message_helpers(): void
    {
        $paymentRequest = $this->makePaymentRequest([
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $this->mock(SettingsService::class, function ($mock) {
            $mock->shouldReceive('getByKey')->andReturn('pk_test_dummy');
        });

        $this->get(route('pay.show', [
            'uuid' => $paymentRequest->uuid,
            'embed' => '1',
            'return_url' => 'https://loja.exemplo.com/obrigado',
        ]))
            ->assertOk()
            ->assertSee('isEmbedHint: true', false)
            ->assertSee('payment_request.completed', false)
            ->assertSee('loja.exemplo.com', false)
            ->assertSee('maybeRedirectReturnUrl', false);
    }

    public function test_pay_page_embed_checkout_uses_redirect_if_required(): void
    {
        $paymentRequest = $this->makePaymentRequest();

        $this->mock(StripeGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('createPaymentIntentWithMode')
                ->once()
                ->andReturn([
                    'status' => 'success',
                    'data' => [
                        'id' => 'pi_test_embed',
                        'client_secret' => 'pi_test_embed_secret',
                    ],
                ]);
        });

        $this->mock(SettingsService::class, function ($mock) {
            $mock->shouldReceive('getByKey')->andReturn('pk_test_dummy');
        });

        $this->get(route('pay.show', [
            'uuid' => $paymentRequest->uuid,
            'embed' => '1',
        ]))
            ->assertOk()
            ->assertSee('isEmbedHint: true', false)
            ->assertSee('if_required', false);
    }

    public function test_pay_page_rejects_invalid_return_url(): void
    {
        $paymentRequest = $this->makePaymentRequest([
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $this->mock(SettingsService::class, function ($mock) {
            $mock->shouldReceive('getByKey')->andReturn('pk_test_dummy');
        });

        $this->get(route('pay.show', [
            'uuid' => $paymentRequest->uuid,
            'return_url' => 'javascript:alert(1)',
        ]))
            ->assertOk()
            ->assertDontSee('javascript:alert(1)', false);
    }

    public function test_pay_page_inhibits_new_payment_when_local_intent_is_processing(): void
    {
        $paymentRequest = $this->makePaymentRequest(['status' => 'processing']);
        $this->attachLocalPaymentIntent($paymentRequest, 'processing');

        $this->mock(StripeGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('createPaymentIntentWithMode')->never();
            $mock->shouldReceive('getPaymentIntentWithMode')->never();
        });

        $this->mock(SettingsService::class, function ($mock) {
            $mock->shouldReceive('getByKey')->andReturn('pk_test_dummy');
        });

        $this->get(route('pay.show', $paymentRequest->uuid))
            ->assertOk()
            ->assertSee('A confirmar pagamento')
            ->assertDontSee('id="payment-form"', false)
            ->assertDontSee('Pagar 15,00 EUR');
    }

    public function test_pay_page_marks_request_cancelled_when_local_intent_is_canceled(): void
    {
        $paymentRequest = $this->makePaymentRequest(['status' => 'processing']);
        $this->attachLocalPaymentIntent($paymentRequest, 'canceled');

        $this->mock(StripeGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('createPaymentIntentWithMode')->never();
            $mock->shouldReceive('getPaymentIntentWithMode')->never();
        });

        $this->mock(SettingsService::class, function ($mock) {
            $mock->shouldReceive('getByKey')->andReturn('pk_test_dummy');
        });

        $this->get(route('pay.show', $paymentRequest->uuid))
            ->assertOk()
            ->assertSee('Este link foi cancelado.')
            ->assertDontSee('id="payment-form"', false);

        $this->assertDatabaseHas('merchant_payment_requests', [
            'id' => $paymentRequest->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_pay_page_marks_request_failed_when_local_intent_failed(): void
    {
        $paymentRequest = $this->makePaymentRequest(['status' => 'processing']);
        $this->attachLocalPaymentIntent($paymentRequest, 'failed');

        $this->mock(StripeGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('createPaymentIntentWithMode')->never();
            $mock->shouldReceive('getPaymentIntentWithMode')->never();
        });

        $this->mock(SettingsService::class, function ($mock) {
            $mock->shouldReceive('getByKey')->andReturn('pk_test_dummy');
        });

        $this->get(route('pay.show', $paymentRequest->uuid))
            ->assertOk()
            ->assertSee('Pagamento falhado')
            ->assertDontSee('id="payment-form"', false);

        $this->assertDatabaseHas('merchant_payment_requests', [
            'id' => $paymentRequest->id,
            'status' => 'failed',
        ]);
    }

    public function test_pay_page_completes_request_when_local_intent_already_succeeded(): void
    {
        $paymentRequest = $this->makePaymentRequest(['status' => 'processing']);
        $this->attachLocalPaymentIntent($paymentRequest, 'succeeded');

        $this->mock(StripeGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('createPaymentIntentWithMode')->never();
            $mock->shouldReceive('getPaymentIntentWithMode')->never();
        });

        $this->mock(SettingsService::class, function ($mock) {
            $mock->shouldReceive('getByKey')->andReturn('pk_test_dummy');
        });

        $this->get(route('pay.show', $paymentRequest->uuid))
            ->assertOk()
            ->assertSee('Pagamento concluído')
            ->assertDontSee('id="payment-form"', false);

        $this->assertDatabaseHas('merchant_payment_requests', [
            'id' => $paymentRequest->id,
            'status' => 'completed',
        ]);
    }

    public function test_pay_page_uses_test_publishable_key_when_merchant_is_in_test_mode(): void
    {
        $paymentRequest = $this->makePaymentRequest();
        $this->putStripeKey('stripe.eur.test_public_key', 'pk_test_merchant_page');
        $this->putStripeKey('stripe.eur.public_key', 'pk_live_should_not_appear');

        $this->mock(StripeGatewayInterface::class, function ($mock): void {
            $mock->shouldReceive('createPaymentIntentWithMode')
                ->once()
                ->withArgs(fn (array $payload, string $mode): bool => $mode === 'test')
                ->andReturn([
                    'status' => 'success',
                    'data' => [
                        'id' => 'pi_mode_test',
                        'client_secret' => 'pi_mode_test_secret',
                        'status' => 'requires_payment_method',
                        'amount' => 1500,
                        'currency' => 'eur',
                    ],
                ]);
        });

        $this->get(route('pay.show', $paymentRequest->uuid))
            ->assertOk()
            ->assertSee('pk_test_merchant_page', false)
            ->assertDontSee('pk_live_should_not_appear', false);
    }

    public function test_pay_page_uses_live_publishable_key_when_merchant_is_in_live_mode(): void
    {
        $paymentRequest = $this->makePaymentRequest();
        $paymentRequest->merchant->update(['mode' => 'live']);
        $this->putStripeKey('stripe.eur.public_key', 'pk_live_merchant_page');
        $this->putStripeKey('stripe.eur.test_public_key', 'pk_test_should_not_appear');

        $this->mock(StripeGatewayInterface::class, function ($mock): void {
            $mock->shouldReceive('createPaymentIntentWithMode')
                ->once()
                ->withArgs(fn (array $payload, string $mode): bool => $mode === 'live')
                ->andReturn([
                    'status' => 'success',
                    'data' => [
                        'id' => 'pi_mode_live',
                        'client_secret' => 'pi_mode_live_secret',
                        'status' => 'requires_payment_method',
                        'amount' => 1500,
                        'currency' => 'eur',
                    ],
                ]);
        });

        $this->get(route('pay.show', $paymentRequest->uuid))
            ->assertOk()
            ->assertSee('pk_live_merchant_page', false)
            ->assertDontSee('pk_test_should_not_appear', false);
    }

    private function putStripeKey(string $key, string $value): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['label' => $key, 'type' => 'text', 'value' => $value],
        );
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

    private function attachLocalPaymentIntent(MerchantPaymentRequest $paymentRequest, string $status): void
    {
        $piId = 'pi_local_'.$status.'_'.uniqid();

        $paymentRequest->update([
            'stripe_payment_intent_id' => $piId,
        ]);

        StripePaymentIntent::query()->create([
            'customer_id' => $paymentRequest->merchant->customer_id,
            'stripe_payment_intent_id' => $piId,
            'amount' => $paymentRequest->amount,
            'currency' => $paymentRequest->currency,
            'status' => $status,
            'type' => TransactionType::MERCHANT_PAYMENT->value,
            'purpose' => 'merchant_payment',
            'stripe_metadata' => [
                'id' => $piId,
                'client_secret' => $piId.'_secret',
                'status' => $status,
            ],
        ]);
    }
}
