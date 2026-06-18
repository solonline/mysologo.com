<?php

namespace App\Domains\Wallet\Enums;

enum SettlementBatchStatus: string
{
    case PENDING = 'pending';
    case TRANSFERRED = 'transferred';
    case DISTRIBUTING = 'distributing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
