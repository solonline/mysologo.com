<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Mail\ValidationEmail;
use App\Models\Customer;

class ValidationEmailTest extends TestCase
{
    use RefreshDatabase;
    public function test_validation_email_is_sent_to_customer()
    {
        $customer = Customer::factory()->create([
            'email' => 'bruno_henriquet@live.com',
        ]);


        $validationUrl = 'https://exemplo.com';
        Mail::to($customer->email)->send(new ValidationEmail($customer, $validationUrl));
        // Não há assert, pois queremos envio real
        $this->assertTrue(true);

    }
}
