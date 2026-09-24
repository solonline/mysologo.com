<?php

namespace App\Domains\Push\Listeners;

use App\Domains\Customer\Events\CustomerNotificationCreated;
use App\Domains\Push\Jobs\SendCustomerPushJob;

class QueueCustomerPush
{
    public function handle(CustomerNotificationCreated $event): void
    {
        SendCustomerPushJob::dispatch($event->notification->id)->afterCommit();
    }
}
