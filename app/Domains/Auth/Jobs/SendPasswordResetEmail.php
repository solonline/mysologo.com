<?php

namespace App\Domains\Auth\Jobs;

use App\Models\Customer;
use App\Domains\Auth\Services\PasswordResetService;
use App\Domains\Settings\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendPasswordResetEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $email;
    public string $token;

    public function __construct(string $email, string $token, protected SettingsService $settingsService)
    {
        $this->email = $email;
        $this->token = $token;
    }

    public function handle(): void
    {
        $customer = Customer::where('email', $this->email)->first();
        $infoSystem = $this->settingsService->getInfoSystem();
        if ($customer) {
            Mail::send('emails.password_reset', [
                'token' => $this->token,
                'customer' => $customer,
                'infoSystem' => $infoSystem,
                'resetUrl' => app(PasswordResetService::class)->resetUrl($customer->email, $this->token),
            ], function ($message) use ($customer) {
                $message->to($customer->email);
                $message->subject('Password Reset Request');
            });
        }
    }
}
