<?php

namespace App\Domains\Merchant\Jobs;

use App\Domains\Merchant\Mail\SecurePayWelcomeMail;
use App\Models\Merchant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendSecurePayWelcomeEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(public Merchant $merchant) {}

    public function handle(): void
    {
        $this->merchant->loadMissing('customer');

        $email = $this->merchant->customer?->email;

        if (! is_string($email) || $email === '') {
            return;
        }

        Mail::to($email)->send(new SecurePayWelcomeMail($this->merchant));
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('merchant')->error('SendSecurePayWelcomeEmail: falha ao enviar boas-vindas', [
            'merchant_id' => $this->merchant->id,
            'message' => $exception?->getMessage(),
        ]);
    }
}
