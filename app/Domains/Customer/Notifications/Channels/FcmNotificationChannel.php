<?php

namespace App\Domains\Customer\Notifications\Channels;

use App\Domains\Customer\Notifications\Contracts\CustomerAppNotification;
use App\Domains\Customer\Notifications\Contracts\NotificationChannel;
use App\Domains\Push\Jobs\SendCustomerPushJob;
use App\Models\Customer;
use RuntimeException;

class FcmNotificationChannel implements NotificationChannel
{
    public function send(Customer $customer, CustomerAppNotification $notification): void
    {
        $notificationId = $notification->persistedId();

        if ($notificationId === null) {
            throw new RuntimeException('FcmNotificationChannel requires InApp channel to persist first.');
        }

        SendCustomerPushJob::dispatch($notificationId)->afterCommit();
    }
}
