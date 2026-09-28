<?php

namespace App\Domains\Customer\Jobs;

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

    public function handle(): void
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

        Mail::to($email)->send(new CustomerAppNotificationMail($notification));
    }
}
