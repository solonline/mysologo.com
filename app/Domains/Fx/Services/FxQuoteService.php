<?php

namespace App\Domains\Fx\Services;

use App\Domains\Fx\Contracts\FxGatewayInterface;
use App\Domains\Fx\DTOs\CurrencyQuote;
use Illuminate\Support\Facades\Cache;

class FxQuoteService
{
    public function __construct(
        protected FxGatewayInterface $gateway,
        protected FxSettingsService $settings,
    ) {}

    public function quote(string $from, string $to): CurrencyQuote
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return CurrencyQuote::identity($from);
        }

        return Cache::remember(
            $this->cacheKey($from, $to),
            $this->settings->cacheTtlSeconds(),
            fn () => $this->gateway->quote($from, $to),
        );
    }

    /**
     * @param  list<array{0: string, 1: string}>  $pairs
     * @return list<CurrencyQuote>
     */
    public function quotes(array $pairs): array
    {
        $result = [];

        foreach ($pairs as $pair) {
            $result[] = $this->quote((string) $pair[0], (string) $pair[1]);
        }

        return $result;
    }

    public function convertCents(int $amountCents, string $from, string $to, bool $useAsk = true): int
    {
        return $this->quote($from, $to)->convertCents($amountCents, $useAsk);
    }

    private function cacheKey(string $from, string $to): string
    {
        return "fx:quote:{$from}:{$to}";
    }
}
