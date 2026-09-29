<?php

namespace Tests\Feature\Domains\Auth;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class ReferralRegistrationTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareOnboardingFeatureTest();
    }

    public function test_retail_registration_without_referral_code_leaves_referred_by_null(): void
    {
        $response = $this->postJson('/api/auth/register-retail', [
            'name' => 'New User',
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'phone' => '11999999999',
            'password' => 'password',
            'currency' => 'EUR',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.user.referred_by_customer_id', null);

        $customer = Customer::query()->where('email', 'newuser@example.com')->first();
        $this->assertNotNull($customer);
        $this->assertNull($customer->referred_by_customer_id);
    }

    public function test_retail_registration_with_valid_referral_code_links_referrer(): void
    {
        $referrer = Customer::factory()->create([
            'username' => 'referrer_user',
            'email' => 'referrer@example.com',
        ]);

        $response = $this->postJson('/api/auth/register-retail', [
            'name' => 'Referred User',
            'username' => 'referreduser',
            'email' => 'referred@example.com',
            'phone' => '11988887777',
            'password' => 'password',
            'currency' => 'EUR',
            'referral_code' => 'Referrer_User',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.user.referred_by_customer_id', $referrer->id);
        $response->assertJsonPath('data.user.referred_by.id', $referrer->id);
        $response->assertJsonPath('data.user.referred_by.username', 'referrer_user');

        $customer = Customer::query()->where('email', 'referred@example.com')->first();
        $this->assertNotNull($customer);
        $this->assertSame($referrer->id, $customer->referred_by_customer_id);
    }

    public function test_retail_registration_with_invalid_referral_code_returns_422(): void
    {
        $response = $this->postJson('/api/auth/register-retail', [
            'name' => 'Bad Referral',
            'username' => 'badreferral',
            'email' => 'badreferral@example.com',
            'phone' => '11977776666',
            'password' => 'password',
            'currency' => 'EUR',
            'referral_code' => 'does_not_exist',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['referral_code']);

        $this->assertNull(Customer::query()->where('email', 'badreferral@example.com')->first());
    }
}
