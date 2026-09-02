<?php

namespace App\Domains\Fx\Gateways;

use App\Domains\Fx\Contracts\FxClientInterface;
use App\Domains\Fx\Contracts\FxGatewayInterface;
use App\Domains\Fx\DTOs\CurrencyQuote;
use App\Domains\Fx\Exceptions\FxQuoteException;
use Illuminate\Support\Facades\Log;
use Throwable;

class FxGateway implements FxGatewayInterface
{
    public function __construct(
        protected FxClientInterface $client,
    ) {}

    public function quote(string $from, string $to): CurrencyQuote
    {
        $quotes = $this->quotes([[$from, $to]]);

        return $quotes[0];
    }

    public function quotes(array $pairs): array
    {
        if ($pairs === []) {
            return [];
        }

        $normalized = [];

        foreach ($pairs as $pair) {
            $from = strtoupper((string) ($pair[0] ?? ''));
            $to = strtoupper((string) ($pair[1] ?? ''));

            if ($from === '' || $to === '') {
                throw new FxQuoteException('Par de moedas inválido.');
            }

            $normalized[] = [$from, $to];
        }

        try {
            $quotes = $this->client->fetchQuotes($normalized);
        } catch (FxQuoteException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::channel('fx')->warning('fx_api: communication_error', [
                'pairs' => $normalized,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw new FxQuoteException('Erro de comunicação com o serviço de cotações.', previous: $e);
        }

        Log::channel('fx')->info('fx_api: quotes', [
            'pairs' => $normalized,
            'count' => count($quotes),
        ]);

        return $quotes;
    }
}
