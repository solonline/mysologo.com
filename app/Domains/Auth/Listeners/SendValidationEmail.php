<?php

namespace App\Domains\Auth\Listeners;

use App\Domains\Auth\Events\UserRegistered;
use App\Domains\Auth\Mail\ValidationEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendValidationEmail implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'emails';

    public function handle(UserRegistered $event): void
    {
        Mail::to($event->customer->email)
            ->send(new ValidationEmail($event->customer, $event->validationUrl, $event->infoSystem));
    }
}
