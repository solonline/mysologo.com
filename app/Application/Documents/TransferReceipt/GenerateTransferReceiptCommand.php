<?php

namespace App\Application\Documents\TransferReceipt;

final readonly class GenerateTransferReceiptCommand
{
    public function __construct(
        public int $customerId,
        public int $movementId,
    ) {}
}
