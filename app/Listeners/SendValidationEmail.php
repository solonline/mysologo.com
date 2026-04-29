<?php

namespace App\Listeners;

use App\Events\CustomerRegistered;
use App\Mail\ValidationEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendValidationEmail implements ShouldQueue
{
    use InteractsWithQueue;
    public string $queue = 'emails';
    /**
     * Handle the event.
     */
    public function handle(CustomerRegistered $event): void
    {
        Mail::to($event->customer->email)->send(new ValidationEmail($event->customer, $event->validationUrl, $event->infoSystem));
    }
}
