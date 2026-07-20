<?php

namespace App\Domains\Wallet\Enums;

enum PartnerFundingStatus: string
{
    case NotRequired = 'not_required';
    case Pending = 'pending';
    case AwaitingLiquidity = 'awaiting_liquidity';
    case AwaitingStripePayout = 'awaiting_stripe_payout';
    case Failed = 'failed';
    case Completed = 'completed';

    public function isRetriable(): bool
    {
        return in_array($this, [
            self::Pending,
            self::AwaitingLiquidity,
            self::Failed,
        ], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::NotRequired,
            self::Completed,
        ], true);
    }
}
