<?php

namespace App\Domains\Customer\Enums;

enum CustomerNotificationType: string
{
    case TransferReceived = 'transfer_received';
    case TestPush = 'test_push';

    public function path(string|int|null $resourceId = null): ?string
    {
        if ($resourceId === null || $resourceId === '') {
            return null;
        }

        return match ($this) {
            self::TransferReceived => '/api/wallet/transactions/'.$resourceId,
            self::TestPush => null,
        };
    }
}
