<?php

namespace App\Domains\Wallet\Enums;

enum FundingPartnerMovementStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Canceled = 'canceled';
}
