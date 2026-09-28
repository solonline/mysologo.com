<?php

namespace App\Domains\Customer\Notifications;

use App\Domains\Customer\Notifications\Contracts\CustomerAppNotification;
use App\Domains\Customer\Notifications\Contracts\NotificationChannel;
use App\Models\Customer;
use InvalidArgumentException;

class NotificationDispatcher
{
    public function send(Customer $customer, CustomerAppNotification $notification): void
    {
        foreach ($notification->channels() as $channelClass) {
            $channel = app($channelClass);

            if (! $channel instanceof NotificationChannel) {
                throw new InvalidArgumentException("Invalid notification channel [{$channelClass}].");
            }

            $channel->send($customer, $notification);
        }
    }
}
