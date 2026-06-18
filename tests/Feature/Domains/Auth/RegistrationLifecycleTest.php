<?php

namespace Tests\Feature\Domains\Auth;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class RegistrationLifecycleTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareOnboardingFeatureTest();
    }

    public function test_retail_registration_starts_with_pending_email_verification(): void
    {
        $response = $this->postJson('/api/register-retail', [
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'testuser@example.com',
            'phone' => '11999999999',
            'password' => 'password',
            'password_confirmation' => 'password',
            'type' => 'retail',
            'currency' => 'EUR',
        ]);

        $response->assertCreated();

        $customer = Customer::query()->where('email', 'testuser@example.com')->first();
        $this->assertNotNull($customer);
        $this->assertSame(CustomerLifecycleStatus::PENDING_EMAIL_VERIFICATION, $customer->status);
    }
}
