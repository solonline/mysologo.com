<?php

namespace Tests\Unit\Domains\Wallester;

use App\Domains\Wallester\Clients\WallesterHttpClient;
use App\Domains\Wallester\Gateways\WallesterGateway;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class WallesterGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_create_account_logs_successful_api_response(): void
    {
        $logged = [];
        Log::listen(function (MessageLogged $event) use (&$logged): void {
            $logged[] = [
                'level' => $event->level,
                'message' => $event->message,
                'context' => $event->context,
            ];
        });

        $client = Mockery::mock(WallesterHttpClient::class);
        $client->shouldReceive('forCurrency')->andReturnSelf()->byDefault();
        $client->shouldReceive('createAccount')
            ->once()
            ->with(['name' => 'Mario Rossi'])
            ->andReturn(['id' => 'wal_123', 'currency_code' => 'EUR']);

        $gateway = new WallesterGateway($client);

        $result = $gateway->createAccount(['name' => 'Mario Rossi']);

        $this->assertSame(['id' => 'wal_123', 'currency_code' => 'EUR'], $result);
        $this->assertTrue(
            collect($logged)->contains(
                fn (array $entry) => $entry['level'] === 'info'
                    && $entry['message'] === 'wallester_api: createAccount'
                    && ($entry['context']['response']['id'] ?? null) === 'wal_123',
            ),
        );
    }

    public function test_create_account_logs_http_error_response(): void
    {
        $logged = [];
        Log::listen(function (MessageLogged $event) use (&$logged): void {
            $logged[] = [
                'level' => $event->level,
                'message' => $event->message,
                'context' => $event->context,
            ];
        });

        $exception = new ClientException(
            'Client error',
            new Request('POST', 'https://api-frontend.wallester.com/v1/accounts'),
            new Response(404, [], '{"message":"API endpoint you were trying to reach could not be found"}'),
        );

        $client = Mockery::mock(WallesterHttpClient::class);
        $client->shouldReceive('forCurrency')->andReturnSelf()->byDefault();
        $client->shouldReceive('createAccount')
            ->once()
            ->andThrow($exception);

        $gateway = new WallesterGateway($client);

        try {
            $gateway->createAccount(['name' => 'Mario Rossi']);
            $this->fail('Expected ClientException was not thrown.');
        } catch (ClientException $caught) {
            $this->assertSame(404, $caught->getResponse()?->getStatusCode());
        }

        $this->assertTrue(
            collect($logged)->contains(
                fn (array $entry) => $entry['level'] === 'error'
                    && $entry['message'] === 'wallester_error: createAccount'
                    && ($entry['context']['http_status'] ?? null) === 404
                    && str_contains((string) ($entry['context']['response_body'] ?? ''), 'could not be found'),
            ),
        );
    }

    public function test_create_account_unwraps_nested_account_payload(): void
    {
        $client = Mockery::mock(WallesterHttpClient::class);
        $client->shouldReceive('forCurrency')->andReturnSelf()->byDefault();
        $client->shouldReceive('createAccount')
            ->once()
            ->with(['name' => 'Mario Rossi'])
            ->andReturn([
                'account' => [
                    'id' => '8951efcc-91a7-4f59-977f-c9ad186bfa98',
                    'currency_code' => 'EUR',
                    'product_id' => '9fd2307c-5419-4ccd-a6f7-3d6007bcb4ad',
                ],
            ]);

        $gateway = new WallesterGateway($client);

        $result = $gateway->createAccount(['name' => 'Mario Rossi']);

        $this->assertSame('8951efcc-91a7-4f59-977f-c9ad186bfa98', $result['id']);
        $this->assertSame('EUR', $result['currency_code']);
    }

    public function test_list_accounts_normalizes_response(): void
    {
        $client = Mockery::mock(WallesterHttpClient::class);
        $client->shouldReceive('forCurrency')->andReturnSelf()->byDefault();
        $client->shouldReceive('listAccounts')
            ->once()
            ->with(['from_record' => 0, 'records_count' => 15], '42')
            ->andReturn([
                'total_records_number' => 2,
                'accounts' => [
                    ['id' => 'wal_acc_1'],
                    ['id' => 'wal_acc_2'],
                ],
            ]);

        $gateway = new WallesterGateway($client);

        $result = $gateway->listAccounts(['from_record' => 0, 'records_count' => 15], '42');

        $this->assertSame(2, $result['total_records_number']);
        $this->assertCount(2, $result['accounts']);
    }

    public function test_get_account_unwraps_nested_account_payload(): void
    {
        $client = Mockery::mock(WallesterHttpClient::class);
        $client->shouldReceive('forCurrency')->andReturnSelf()->byDefault();
        $client->shouldReceive('getAccount')
            ->once()
            ->with('27c0b35d-6746-4528-b91c-11e10d851f29', '42')
            ->andReturn([
                'account' => [
                    'id' => '27c0b35d-6746-4528-b91c-11e10d851f29',
                    'currency_code' => 'EUR',
                    'balance' => 99.5,
                ],
            ]);

        $gateway = new WallesterGateway($client);

        $result = $gateway->getAccount('27c0b35d-6746-4528-b91c-11e10d851f29', '42');

        $this->assertSame('27c0b35d-6746-4528-b91c-11e10d851f29', $result['id']);
        $this->assertSame('EUR', $result['currency_code']);
        $this->assertSame(99.5, $result['balance']);
    }

    public function test_create_card_unwraps_nested_card_payload(): void
    {
        $client = Mockery::mock(WallesterHttpClient::class);
        $client->shouldReceive('forCurrency')->andReturnSelf()->byDefault();
        $client->shouldReceive('createCard')
            ->once()
            ->with(['account_id' => 'wal_123', 'type' => 'Virtual'], null)
            ->andReturn([
                'card' => [
                    'id' => 'card_123',
                    'account_id' => 'wal_123',
                    'type' => 'Virtual',
                    'status' => 'Active',
                ],
            ]);

        $gateway = new WallesterGateway($client);

        $result = $gateway->createCard(['account_id' => 'wal_123', 'type' => 'Virtual']);

        $this->assertSame('card_123', $result['id']);
        $this->assertSame('Active', $result['status']);
    }

    public function test_get_card_unwraps_nested_card_payload(): void
    {
        $client = Mockery::mock(WallesterHttpClient::class);
        $client->shouldReceive('forCurrency')->andReturnSelf()->byDefault();
        $client->shouldReceive('getCard')
            ->once()
            ->with('card_123', '42')
            ->andReturn([
                'card' => [
                    'id' => 'card_123',
                    'type' => 'Virtual',
                    'status' => 'Active',
                    'currency_code' => 'EUR',
                ],
            ]);

        $gateway = new WallesterGateway($client);

        $result = $gateway->getCard('card_123', '42');

        $this->assertSame('card_123', $result['id']);
        $this->assertSame('EUR', $result['currency_code']);
    }

    public function test_get_encrypted_card_number_returns_payload_field(): void
    {
        $client = Mockery::mock(WallesterHttpClient::class);
        $client->shouldReceive('forCurrency')->andReturnSelf()->byDefault();
        $client->shouldReceive('getEncryptedCardNumber')
            ->once()
            ->with('card_123', 'public-key', '42')
            ->andReturn([
                'encrypted_card_number' => '-----BEGIN CardNumber MESSAGE-----',
            ]);

        $gateway = new WallesterGateway($client);

        $result = $gateway->getEncryptedCardNumber('card_123', 'public-key', '42');

        $this->assertSame('-----BEGIN CardNumber MESSAGE-----', $result);
    }

    public function test_activate_card_unwraps_nested_card_payload(): void
    {
        $client = Mockery::mock(WallesterHttpClient::class);
        $client->shouldReceive('forCurrency')->andReturnSelf()->byDefault();
        $client->shouldReceive('activateCard')
            ->once()
            ->with('card_123', '42')
            ->andReturn([
                'card' => [
                    'id' => 'card_123',
                    'type' => 'Virtual',
                    'status' => 'Active',
                    'activated_at' => '2026-07-01T12:00:00Z',
                ],
            ]);

        $gateway = new WallesterGateway($client);

        $result = $gateway->activateCard('card_123', '42');

        $this->assertSame('card_123', $result['id']);
        $this->assertSame('Active', $result['status']);
    }

    public function test_list_account_transactions_normalizes_response(): void
    {
        $client = Mockery::mock(WallesterHttpClient::class);
        $client->shouldReceive('forCurrency')->andReturnSelf()->byDefault();
        $client->shouldReceive('listAccountTransactions')
            ->once()
            ->with('wal_acc_1', ['from_record' => 0, 'records_count' => 15], '42')
            ->andReturn([
                'total_records_number' => 2,
                'transactions' => [
                    ['id' => 'tx_1'],
                    ['id' => 'tx_2'],
                ],
            ]);

        $gateway = new WallesterGateway($client);

        $result = $gateway->listAccountTransactions('wal_acc_1', ['from_record' => 0, 'records_count' => 15], '42');

        $this->assertSame(2, $result['total_records_number']);
        $this->assertCount(2, $result['transactions']);
    }

    public function test_get_transaction_unwraps_nested_transaction_payload(): void
    {
        $client = Mockery::mock(WallesterHttpClient::class);
        $client->shouldReceive('forCurrency')->andReturnSelf()->byDefault();
        $client->shouldReceive('getTransaction')
            ->once()
            ->with('tx_123', '42', [])
            ->andReturn([
                'transaction' => [
                    'id' => 'tx_123',
                    'account_id' => 'wal_acc_1',
                    'amount' => -9.99,
                ],
            ]);

        $gateway = new WallesterGateway($client);

        $result = $gateway->getTransaction('tx_123', '42');

        $this->assertSame('tx_123', $result['id']);
        $this->assertSame(-9.99, $result['amount']);
    }

    public function test_account_transfer_unwraps_nested_payment_payload(): void
    {
        $client = Mockery::mock(WallesterHttpClient::class);
        $client->shouldReceive('forCurrency')->andReturnSelf()->byDefault();
        $client->shouldReceive('accountTransfer')
            ->once()
            ->with([
                'from_account_id' => 'wal_from',
                'to_account_id' => 'wal_to',
                'amount' => 10.5,
                'description' => 'Transfer',
            ], '42')
            ->andReturn([
                'payment' => [
                    'id' => 'pay_123',
                    'status' => 'completed',
                ],
            ]);

        $gateway = new WallesterGateway($client);

        $result = $gateway->accountTransfer([
            'from_account_id' => 'wal_from',
            'to_account_id' => 'wal_to',
            'amount' => 10.5,
            'description' => 'Transfer',
        ], '42');

        $this->assertSame('pay_123', $result['id']);
        $this->assertSame('completed', $result['status']);
    }
}
