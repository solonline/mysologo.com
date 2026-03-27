<?php

namespace Tests\Feature;

use App\Mail\ValidationEmail;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegisterSendsValidationEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_sends_validation_email()
    {
        Mail::fake();

        $data = [
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'testuser@email.com',
            'phone' => '11999999999',
            'password' => 'password',
            'password_confirmation' => 'password',
            'type' => 'retail',
        ];

        $response = $this->postJson('/api/register-retail', $data);
        $response->assertStatus(201);

        $customer = Customer::where('email', $data['email'])->first();
        Mail::assertSent(ValidationEmail::class, function ($mail) use ($customer) {
            return $mail->hasTo($customer->email) && !empty($mail->validationUrl);
        });
    }
}
