<?php

namespace App\Domains\Wallet\Enums;

enum FundingPartnerMovementSource: string
{
    case Api = 'api';
    case Webhook = 'webhook';
    case Sync = 'sync';
}
