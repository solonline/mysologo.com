<?php

namespace App\Domains\Kyc\Jobs;

use App\Domains\Kyc\Mail\DocumentStatusMail;
use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendDocumentStatusEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Customer $customer,
        public string $status,
    ) {}

    public function handle(): void
    {
        Mail::to($this->customer->email)
            ->send(new DocumentStatusMail($this->customer, $this->status));
    }
}
