<?php

namespace App\Domains\Backoffice\Enums;

enum CustomerBalanceAdjustmentDirection: string
{
    case Credit = 'credit';
    case Debit = 'debit';

    public function label(): string
    {
        return match ($this) {
            self::Credit => 'Crédito',
            self::Debit => 'Débito',
        };
    }
}
