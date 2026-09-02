<?php

namespace Tests\Unit\Domains\Fx;

use App\Domains\Fx\Contracts\FxClientInterface;
use App\Domains\Fx\DTOs\CurrencyQuote;
use App\Domains\Fx\Exceptions\FxQuoteException;
use App\Domains\Fx\Gateways\FxGateway;
use Carbon\CarbonImmutable;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class FxGatewayTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_normalizes_pairs_and_delegates_to_client(): void
    {
        $quote = $this->quote();

        $client = Mockery::mock(FxClientInterface::class);
        $client->shouldReceive('fetchQuotes')
            ->once()
            ->with([['USD', 'BRL']])
            ->andReturn([$quote]);

        $result = (new FxGateway($client))->quote('usd', 'brl');

        $this->assertSame($quote, $result);
    }

    public function test_rejects_invalid_pairs_without_calling_client(): void
    {
        $client = Mockery::mock(FxClientInterface::class);
        $client->shouldNotReceive('fetchQuotes');

        $this->expectException(FxQuoteException::class);
        $this->expectExceptionMessage('Par de moedas inválido.');

        (new FxGateway($client))->quotes([['', 'BRL']]);
    }

    public function test_wraps_unexpected_client_errors(): void
    {
        $client = Mockery::mock(FxClientInterface::class);
        $client->shouldReceive('fetchQuotes')
            ->once()
            ->andThrow(new RuntimeException('connection reset'));

        $this->expectException(FxQuoteException::class);
        $this->expectExceptionMessage('Erro de comunicação com o serviço de cotações.');

        (new FxGateway($client))->quote('USD', 'BRL');
    }

    public function test_rethrows_fx_quote_exception_from_client(): void
    {
        $client = Mockery::mock(FxClientInterface::class);
        $client->shouldReceive('fetchQuotes')
            ->once()
            ->andThrow(new FxQuoteException('moeda nao encontrada ABC-BRL'));

        $this->expectException(FxQuoteException::class);
        $this->expectExceptionMessage('moeda nao encontrada ABC-BRL');

        (new FxGateway($client))->quote('ABC', 'BRL');
    }

    private function quote(): CurrencyQuote
    {
        return new CurrencyQuote(
            from: 'USD',
            to: 'BRL',
            bid: '5.72',
            ask: '5.73',
            high: '5.73',
            low: '5.72',
            pctChange: '0',
            name: 'USD/BRL',
            quotedAt: CarbonImmutable::createFromTimestamp(1618315045),
            provider: 'awesomeapi',
        );
    }
}
