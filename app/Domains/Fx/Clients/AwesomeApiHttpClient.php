<?php

namespace App\Domains\Fx\Clients;

use App\Domains\Fx\Contracts\FxClientInterface;
use App\Domains\Fx\DTOs\CurrencyQuote;
use App\Domains\Fx\Exceptions\FxQuoteException;
use App\Domains\Fx\Services\FxSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AwesomeApiHttpClient implements FxClientInterface
{
    public const PROVIDER = 'awesomeapi';

    public function __construct(
        protected FxSettingsService $settings,
    ) {}

    public function fetchQuotes(array $pairs): array
    {
        $formatted = [];

        foreach ($pairs as $pair) {
            $formatted[] = $pair[0].'-'.$pair[1];
        }

        $response = $this->request()->get('/json/last/'.implode(',', $formatted));
        $body = $response->json();

        if ($this->isMissingPair($response, $body)) {
            $message = is_array($body)
                ? (string) ($body['message'] ?? 'Par de moedas não encontrado.')
                : 'Par de moedas não encontrado.';

            Log::channel('fx')->warning('fx_api: pair_not_found', [
                'pairs' => $formatted,
                'status' => $response->status(),
                'body' => $body,
            ]);

            throw new FxQuoteException($message);
        }

        if (! $response->successful() || ! is_array($body)) {
            Log::channel('fx')->warning('fx_api: unexpected_response', [
                'pairs' => $formatted,
                'status' => $response->status(),
                'body' => $body,
            ]);

            throw new FxQuoteException('Resposta inválida do serviço de cotações.');
        }

        $quotes = [];

        foreach ($pairs as $pair) {
            $from = $pair[0];
            $to = $pair[1];
            $payload = $body[$from.$to] ?? null;

            if (! is_array($payload)) {
                throw new FxQuoteException("Cotação {$from}-{$to} não encontrada na resposta.");
            }

            $quotes[] = $this->mapQuote($payload);
        }

        return $quotes;
    }

    private function request(): PendingRequest
    {
        $request = Http::timeout(10)
            ->acceptJson()
            ->baseUrl($this->settings->baseUrl());

        $apiKey = $this->settings->apiKey();

        if ($apiKey !== '') {
            $request = $request->withHeaders(['x-api-key' => $apiKey]);
        }

        return $request;
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function isMissingPair(Response $response, mixed $body): bool
    {
        if ($response->status() === 404) {
            return true;
        }

        return is_array($body)
            && (($body['code'] ?? null) === 'CoinNotExists' || (int) ($body['status'] ?? 0) === 404);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapQuote(array $payload): CurrencyQuote
    {
        $from = strtoupper((string) ($payload['code'] ?? ''));
        $to = strtoupper((string) ($payload['codein'] ?? ''));
        $bid = (string) ($payload['bid'] ?? '');
        $ask = (string) ($payload['ask'] ?? '');

        if ($from === '' || $to === '' || $bid === '' || $ask === '') {
            throw new FxQuoteException('Payload de cotação incompleto.');
        }

        $timestamp = (int) ($payload['timestamp'] ?? 0);

        if ($timestamp > 9_999_999_999) {
            $timestamp = intdiv($timestamp, 1000);
        }

        $quotedAt = $timestamp > 0
            ? CarbonImmutable::createFromTimestamp($timestamp)
            : CarbonImmutable::now();

        return new CurrencyQuote(
            from: $from,
            to: $to,
            bid: $bid,
            ask: $ask,
            high: (string) ($payload['high'] ?? $bid),
            low: (string) ($payload['low'] ?? $bid),
            pctChange: (string) ($payload['pctChange'] ?? '0'),
            name: (string) ($payload['name'] ?? $from.'/'.$to),
            quotedAt: $quotedAt,
            provider: self::PROVIDER,
        );
    }
}
