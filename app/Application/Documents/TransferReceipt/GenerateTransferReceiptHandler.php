<?php

namespace App\Application\Documents\TransferReceipt;

use App\Domains\Documents\Contracts\PdfRenderer;
use App\Domains\Wallet\Contracts\TransactionRepositoryInterface;

final class GenerateTransferReceiptHandler
{
    public function __construct(
        private TransactionRepositoryInterface $transactionRepository,
        private PdfRenderer $pdfRenderer,
    ) {}

    public function handle(
        GenerateTransferReceiptCommand $command
    ): string {
        $movement = $this->transactionRepository->getTransfersP2pById(
            $command->movementId
        );

        $data = TransferReceiptData::fromMovement($movement);

        $html = view(
            'pdf.transfer-receipt',
            ['data' => $data]
        )->render();

        return $this->pdfRenderer->render($html);
    }
}
