<?php

namespace App\Domains\Backoffice\Enums;

enum CustomerBalanceAdjustmentStatus: string
{
    case PendingApproval = 'pending_approval';
    case Executed = 'executed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
