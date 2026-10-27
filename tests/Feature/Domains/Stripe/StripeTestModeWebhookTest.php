<?php

namespace Tests\Feature\Domains\Stripe;

use App\Domains\Stripe\Clients\StripeHttpClient;
use App\Domains\Stripe\Contracts\StripeGatewayInterface;
use App\Domains\Stripe\Jobs\ProcessStripeWebhook;
use App\Domains\Stripe\Services\StripeService;
use App\Models\StripeTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Stripe\Event;
use Tests\TestCase;

class StripeTestModeWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_test_mode_webhook_is_acknowledged_and_not_queued(): void
    {
        Bus::fake();

        $event = Event::constructFrom([
            'id' => 'evt_test_ignored',
            'object' => 'event',
            'api_version' => '2026-04-22',
            'created' => time(),
            'type' => 'payment_intent.succeeded',
            'livemode' => false,
            'pending_webhooks' => 1,
            'request' => ['id' => null, 'idempotency_key' => null],
            'data' => [
                'object' => [
                    'id' => 'pi_should_not_run',
                    'object' => 'payment_intent',
                    'livemode' => false,
                    'status' => 'succeeded',
                ],
            ],
        ]);

        $client = Mockery::mock(StripeHttpClient::class);
        $client->shouldReceive('constructWebhookEvent')->once()->andReturn($event);
        $this->app->instance(StripeHttpClient::class, $client);

        $this->postJson('/api/webhooks/stripe', [], ['Stripe-Signature' => 't=1,v1=test'])
            ->assertOk()
            ->assertJson(['success' => true]);

        Bus::assertNotDispatched(ProcessStripeWebhook::class);
        $this->assertSame(0, StripeTransaction::query()->count());
    }

    public function test_test_mode_event_does_not_create_a_topup(): void
    {
        $gateway = Mockery::mock(StripeGatewayInterface::class);
        $gateway->shouldNotReceive('createPaymentIntent');
        $this->app->instance(StripeGatewayInterface::class, $gateway);

        app(StripeService::class)->handleWebhook([
            'id' => 'evt_cash_test',
            'type' => 'customer_cash_balance_transaction.created',
            'livemode' => false,
            'data' => [
                'object' => [
                    'id' => 'ccsbtxn_test_ignored',
                    'object' => 'customer_cash_balance_transaction',
                    'livemode' => false,
                    'type' => 'funded',
                    'net_amount' => 100,
                    'currency' => 'eur',
                    'customer' => 'cus_test',
                ],
            ],
        ]);

        $this->assertSame(0, StripeTransaction::query()->count());
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
