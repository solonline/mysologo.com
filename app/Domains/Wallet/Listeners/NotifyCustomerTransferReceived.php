<?php

namespace App\Domains\Wallet\Listeners;

use App\Domains\Customer\Notifications\NotificationDispatcher;
use App\Domains\Customer\Notifications\TransferReceivedNotification;
use App\Domains\Customer\Notifications\TransferSentNotification;
use App\Domains\Wallet\Events\CustomerTransferReceived;
use App\Models\Customer;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyCustomerTransferReceived implements ShouldQueue
{
    public function __construct(
        protected NotificationDispatcher $dispatcher,
    ) {}

    public function handle(CustomerTransferReceived $event): void
    {
        $to = Customer::query()->find($event->toCustomerId);
        $from = Customer::query()->find($event->fromCustomerId);

        if ($to === null || $from === null) {
            return;
        }

        $amountLabel = number_format($event->amountCents / 100, 2, ',', '.').' '.$event->currency;
        $sender = (string) ($from->username ?: $from->name);

        $this->dispatcher->send(
            $to,
            new TransferReceivedNotification(
                sender: $sender,
                amountLabel: $amountLabel,
                description: filled($event->description) ? (string) $event->description : null,
                movementId: $event->movementId,
                extra: [
                    'amount_cents' => $event->amountCents,
                    'currency' => $event->currency,
                    'from_customer_id' => $event->fromCustomerId,
                    'amount_label' => $amountLabel,
                    'sender' => $sender,
                ],
            ),
        );

        if ($event->sendBothSides) {
            $recipient = (string) ($to->username ?: $to->name);

            $this->dispatcher->send(
                $from,
                new TransferSentNotification(
                    recipient: $recipient,
                    amountLabel: $amountLabel,
                    description: filled($event->description) ? (string) $event->description : null,
                    movementId: $event->movementId,
                    extra: [
                        'amount_cents' => $event->amountCents,
                        'currency' => $event->currency,
                        'to_customer_id' => $event->toCustomerId,
                        'amount_label' => $amountLabel,
                        'recipient' => $recipient,
                    ],
                ),
            );
        }
    }
}
