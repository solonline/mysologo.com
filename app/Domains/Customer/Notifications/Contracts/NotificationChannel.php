<?php

namespace App\Domains\Customer\Notifications\Contracts;

use App\Models\Customer;

interface NotificationChannel
{
    public function send(Customer $customer, CustomerAppNotification $notification): void;
}
