<?php

namespace App\Domains\Fx\Contracts;

use App\Domains\Fx\DTOs\CurrencyQuote;

/**
 * Contrato agnóstico de provedor de cotações (AwesomeAPI hoje, outro amanhã).
 */
interface FxGatewayInterface
{
    public function quote(string $from, string $to): CurrencyQuote;

    /**
     * @param  list<array{0: string, 1: string}>  $pairs
     * @return list<CurrencyQuote>
     */
    public function quotes(array $pairs): array;
}
