<?php
namespace App\Jobs;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use App\Mail\DocumentStatusMail;

class SendDocumentStatusEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Customer $customer;
    public string $status;

    public function __construct(Customer $customer, string $status)
    {
        $this->customer = $customer;
        $this->status = $status;
    }

    public function handle()
    {
        Mail::to($this->customer->email)
            ->send(new DocumentStatusMail($this->customer, $this->status));
    }
}
