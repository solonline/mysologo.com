<?php

namespace Tests\Unit\Domains\Wise;

use App\Domains\Wallet\Enums\CustomerMovementStatus;
use App\Domains\Wise\Enums\WiseTransferStatus;
use PHPUnit\Framework\TestCase;

class WiseTransferCustomerStatusTest extends TestCase
{
    public function test_maps_every_wise_state_to_a_customer_status(): void
    {
        $expected = [
            WiseTransferStatus::IncomingPaymentWaiting->value => CustomerMovementStatus::Pending,
            WiseTransferStatus::IncomingPaymentInitiated->value => CustomerMovementStatus::Pending,
            WiseTransferStatus::Processing->value => CustomerMovementStatus::Processing,
            WiseTransferStatus::FundsConverted->value => CustomerMovementStatus::Processing,
            WiseTransferStatus::OutgoingPaymentSent->value => CustomerMovementStatus::Sent,
            WiseTransferStatus::ChargedBack->value => CustomerMovementStatus::ChargedBack,
            WiseTransferStatus::Cancelled->value => CustomerMovementStatus::Canceled,
            WiseTransferStatus::FundsRefunded->value => CustomerMovementStatus::Refunded,
            WiseTransferStatus::BouncedBack->value => CustomerMovementStatus::BouncedBack,
            WiseTransferStatus::Unknown->value => CustomerMovementStatus::Unknown,
        ];

        foreach (WiseTransferStatus::cases() as $status) {
            $this->assertArrayHasKey($status->value, $expected);
            $this->assertSame($expected[$status->value], $status->customerStatus());
        }
    }
}
