<?php

namespace App\Domains\Fx\DTOs;

use Carbon\CarbonImmutable;

readonly class CurrencyQuote
{
    public function __construct(
        public string $from,
        public string $to,
        public string $bid,
        public string $ask,
        public string $high,
        public string $low,
        public string $pctChange,
        public string $name,
        public CarbonImmutable $quotedAt,
        public string $provider,
    ) {}

    public static function identity(string $currency): self
    {
        $currency = strtoupper($currency);

        return new self(
            from: $currency,
            to: $currency,
            bid: '1',
            ask: '1',
            high: '1',
            low: '1',
            pctChange: '0',
            name: $currency.'/'.$currency,
            quotedAt: CarbonImmutable::now(),
            provider: 'identity',
        );
    }

    public function convertCents(int $amountCents, bool $useAsk = true): int
    {
        $rate = $useAsk ? $this->ask : $this->bid;

        return (int) round($amountCents * (float) $rate);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'bid' => $this->bid,
            'ask' => $this->ask,
            'high' => $this->high,
            'low' => $this->low,
            'pct_change' => $this->pctChange,
            'name' => $this->name,
            'quoted_at' => $this->quotedAt->toIso8601String(),
            'provider' => $this->provider,
        ];
    }
}
