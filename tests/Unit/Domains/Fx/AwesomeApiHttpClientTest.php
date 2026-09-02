<?php

namespace Tests\Unit\Domains\Fx;

use App\Domains\Fx\Clients\AwesomeApiHttpClient;
use App\Domains\Fx\Exceptions\FxQuoteException;
use App\Domains\Fx\Services\FxSettingsService;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class AwesomeApiHttpClientTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_maps_last_quote_and_sends_api_key_header(): void
    {
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            $this->assertSame('https://economia.awesomeapi.com.br/json/last/USD-BRL', $request->url());
            $this->assertTrue($request->hasHeader('x-api-key', 'test-key'));

            return Http::response([
                'USDBRL' => [
                    'code' => 'USD',
                    'codein' => 'BRL',
                    'name' => 'Dólar Americano/Real Brasileiro',
                    'high' => '5.734',
                    'low' => '5.7279',
                    'varBid' => '-0.0054',
                    'pctChange' => '-0.09',
                    'bid' => '5.7276',
                    'ask' => '5.7282',
                    'timestamp' => '1618315045',
                    'create_date' => '2021-04-13 08:57:27',
                ],
            ], 200);
        });

        $quotes = $this->client(apiKey: 'test-key')->fetchQuotes([['USD', 'BRL']]);
        $quote = $quotes[0];

        $this->assertSame('USD', $quote->from);
        $this->assertSame('BRL', $quote->to);
        $this->assertSame('5.7276', $quote->bid);
        $this->assertSame('5.7282', $quote->ask);
        $this->assertSame('awesomeapi', $quote->provider);
        $this->assertSame(1618315045, $quote->quotedAt->timestamp);
        $this->assertSame(57282, $quote->convertCents(10_000));
    }

    public function test_maps_multiple_pairs_in_one_request(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://economia.awesomeapi.com.br/json/last/USD-BRL,EUR-BRL' => Http::response([
                'USDBRL' => $this->pairPayload('USD', 'BRL', '5.72', '5.73'),
                'EURBRL' => $this->pairPayload('EUR', 'BRL', '6.81', '6.82'),
            ], 200),
        ]);

        $quotes = $this->client()->fetchQuotes([['USD', 'BRL'], ['EUR', 'BRL']]);

        $this->assertCount(2, $quotes);
        $this->assertSame('USD', $quotes[0]->from);
        $this->assertSame('EUR', $quotes[1]->from);
        $this->assertSame('6.82', $quotes[1]->ask);
    }

    public function test_throws_when_pair_does_not_exist(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://economia.awesomeapi.com.br/json/last/ABC-BRL' => Http::response([
                'status' => 404,
                'code' => 'CoinNotExists',
                'message' => 'moeda nao encontrada ABC-BRL',
            ], 404),
        ]);

        $this->expectException(FxQuoteException::class);
        $this->expectExceptionMessage('moeda nao encontrada ABC-BRL');

        $this->client()->fetchQuotes([['ABC', 'BRL']]);
    }

    private function client(string $apiKey = ''): AwesomeApiHttpClient
    {
        $settings = Mockery::mock(FxSettingsService::class);
        $settings->shouldReceive('baseUrl')->andReturn('https://economia.awesomeapi.com.br');
        $settings->shouldReceive('apiKey')->andReturn($apiKey);

        return new AwesomeApiHttpClient($settings);
    }

    /**
     * @return array<string, string>
     */
    private function pairPayload(string $from, string $to, string $bid, string $ask): array
    {
        return [
            'code' => $from,
            'codein' => $to,
            'name' => "{$from}/{$to}",
            'high' => $ask,
            'low' => $bid,
            'varBid' => '0',
            'pctChange' => '0',
            'bid' => $bid,
            'ask' => $ask,
            'timestamp' => '1618315045',
            'create_date' => '2021-04-13 08:57:27',
        ];
    }
}
