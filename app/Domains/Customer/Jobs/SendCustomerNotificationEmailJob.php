<?php

namespace App\Domains\Customer\Jobs;

use App\Application\Documents\TransferReceipt\GenerateTransferReceiptCommand;
use App\Application\Documents\TransferReceipt\GenerateTransferReceiptHandler;
use App\Domains\Customer\Mail\CustomerAppNotificationMail;
use App\Models\CustomerNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendCustomerNotificationEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $notificationId,
    ) {
        $this->onQueue('emails');
    }

    public function handle(?GenerateTransferReceiptHandler $generateTransferReceiptHandler = null): void
    {
        $notification = CustomerNotification::query()
            ->with('customer')
            ->find($this->notificationId);

        if ($notification === null || $notification->customer === null) {
            return;
        }

        $email = trim((string) $notification->customer->email);

        if ($email === '') {
            return;
        }

        $pdf = '';
        if (in_array($notification->type, ['transfer_received', 'transfer_sent'], true)) {
            $movementId = (int) ($notification->data['resource_id'] ?? 0);

            if ($movementId > 0) {
                $pdf = ($generateTransferReceiptHandler ?? app(GenerateTransferReceiptHandler::class))->handle(
                    new GenerateTransferReceiptCommand(
                        customerId: (int) $notification->customer_id,
                        movementId: $movementId,
                    )
                );
            }
        }

        Mail::to($email)->send(new CustomerAppNotificationMail($notification, $pdf));
    }
}
