<?php

namespace App\Domains\Fx\Contracts;

use App\Domains\Fx\DTOs\CurrencyQuote;

/**
 * Acesso ao provedor de cotações. Cada API tem o seu client.
 */
interface FxClientInterface
{
    /**
     * @param  list<array{0: string, 1: string}>  $pairs  pares já em maiúsculas
     * @return list<CurrencyQuote>
     */
    public function fetchQuotes(array $pairs): array;
}
