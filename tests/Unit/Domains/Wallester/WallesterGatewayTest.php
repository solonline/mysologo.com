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
}
