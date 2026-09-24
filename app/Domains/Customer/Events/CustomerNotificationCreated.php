<?php

namespace App\Domains\Customer\Events;

use App\Models\CustomerNotification;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerNotificationCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly CustomerNotification $notification,
    ) {}
}
