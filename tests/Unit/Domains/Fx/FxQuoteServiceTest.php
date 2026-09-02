<?php

namespace Tests\Unit\Domains\Fx;

use App\Domains\Fx\Contracts\FxGatewayInterface;
use App\Domains\Fx\DTOs\CurrencyQuote;
use App\Domains\Fx\Gateways\FxGateway;
use App\Domains\Fx\Services\FxQuoteService;
use App\Domains\Fx\Services\FxSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class FxQuoteServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_container_binds_generic_gateway(): void
    {
        $this->assertInstanceOf(FxGateway::class, $this->app->make(FxGatewayInterface::class));
        $this->assertInstanceOf(FxQuoteService::class, $this->app->make(FxQuoteService::class));
    }

    public function test_same_currency_does_not_call_gateway(): void
    {
        $gateway = Mockery::mock(FxGatewayInterface::class);
        $gateway->shouldNotReceive('quote');

        $quote = $this->service($gateway)->quote('eur', 'EUR');

        $this->assertSame('EUR', $quote->from);
        $this->assertSame('EUR', $quote->to);
        $this->assertSame('1', $quote->ask);
        $this->assertSame('identity', $quote->provider);
        $this->assertSame(2500, $quote->convertCents(2500));
    }

    public function test_quote_is_cached(): void
    {
        Cache::flush();

        $payload = new CurrencyQuote(
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

        $gateway = Mockery::mock(FxGatewayInterface::class);
        $gateway->shouldReceive('quote')
            ->once()
            ->with('USD', 'BRL')
            ->andReturn($payload);

        $service = $this->service($gateway);

        $first = $service->quote('usd', 'brl');
        $second = $service->quote('USD', 'BRL');

        $this->assertSame($payload, $first);
        $this->assertSame($payload, $second);
        $this->assertSame(5730, $service->convertCents(1000, 'USD', 'BRL'));
    }

    private function service(FxGatewayInterface $gateway): FxQuoteService
    {
        $settings = Mockery::mock(FxSettingsService::class);
        $settings->shouldReceive('cacheTtlSeconds')->andReturn(60);

        return new FxQuoteService($gateway, $settings);
    }
}
