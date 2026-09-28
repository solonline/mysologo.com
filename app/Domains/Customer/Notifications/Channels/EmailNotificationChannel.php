<?php

namespace App\Domains\Customer\Notifications\Channels;

use App\Domains\Customer\Jobs\SendCustomerNotificationEmailJob;
use App\Domains\Customer\Notifications\Contracts\CustomerAppNotification;
use App\Domains\Customer\Notifications\Contracts\NotificationChannel;
use App\Models\Customer;
use RuntimeException;

class EmailNotificationChannel implements NotificationChannel
{
    public function send(Customer $customer, CustomerAppNotification $notification): void
    {
        $notificationId = $notification->persistedId();

        if ($notificationId === null) {
            throw new RuntimeException('EmailNotificationChannel requires InApp channel to persist first.');
        }

        $email = trim((string) $customer->email);

        if ($email === '') {
            return;
        }

        SendCustomerNotificationEmailJob::dispatch($notificationId)->afterCommit();
    }
}
