<?php
namespace App\Jobs;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use App\Models\User;

class SendPasswordResetEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $email;
    public string $token;

    public function __construct(string $email, string $token)
    {
        $this->email = $email;
        $this->token = $token;
    }

    public function handle()
    {
        $customer = Customer::where('email', $this->email)->first();
        if ($customer) {
            Mail::send('emails.password_reset', [
                'token' => $this->token,
                'customer' => $customer,
            ], function ($message) use ($customer) {
                $message->to($customer->email);
                $message->subject('Password Reset Request');
            });
        }
    }
}
