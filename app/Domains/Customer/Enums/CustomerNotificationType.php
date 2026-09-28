<?php

namespace App\Domains\Customer\Enums;

enum CustomerNotificationType: string
{
    case TransferReceived = 'transfer_received';
    case TopupImmediate = 'topup_immediate';
    case TestPush = 'test_push';

    public function path(string|int|null $resourceId = null): ?string
    {
        if ($resourceId === null || $resourceId === '') {
            return null;
        }

        return match ($this) {
            self::TransferReceived, self::TopupImmediate => 'api/wallet/transactions/'.$resourceId,
            self::TestPush => null,
        };
    }
}
