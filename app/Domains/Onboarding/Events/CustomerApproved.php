<?php

namespace App\Domains\Onboarding\Events;

use App\Models\Customer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(public Customer $customer) {}
}
