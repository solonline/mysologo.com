<?php

namespace App\Domains\Stripe\Enums;

enum StripeFundingStatus: string
{
    case PENDING = 'PENDING';
    case ACTIVE = 'ACTIVE';
    case FAILED = 'FAILED';

    public function isReady(): bool
    {
        return $this === self::ACTIVE;
    }

    public function canRetry(): bool
    {
        return in_array($this, [self::PENDING, self::FAILED], true);
    }
}
