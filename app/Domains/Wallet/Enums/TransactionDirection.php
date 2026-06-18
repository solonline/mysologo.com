<?php

namespace App\Domains\Wallet\Enums;

enum TransactionDirection: string
{
    case Credit = 'credit';
    case Debit = 'debit';
}
