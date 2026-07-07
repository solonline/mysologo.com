<?php

namespace App\Domains\Wallet\Enums;

enum FundingPartnerMovementDirection: string
{
    case In = 'in';
    case Out = 'out';
}
