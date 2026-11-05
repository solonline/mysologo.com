<?php

namespace Tests\Feature\Domains\Merchant;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class CustomerMerchantTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_customer_cannot_register_or_manage_merchants(): void
    {
        $customer = $this->createActiveCustomer();
        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/merchant/register', ['name' => 'Loja da Ana'])
            ->assertNotFound();

        $this->withToken($token)->getJson('/api/merchant')->assertNotFound();
        $this->withToken($token)->getJson('/api/merchant/payment-requests')->assertMethodNotAllowed();
        $this->withToken($token)->patchJson('/api/merchant/1', ['name' => 'Loja'])->assertNotFound();
        $this->withToken($token)->deleteJson('/api/merchant/1')->assertNotFound();
        $this->withToken($token)->getJson('/api/merchant/1/credentials')->assertNotFound();
        $this->withToken($token)
            ->postJson('/api/merchant/1/credentials/rotate', ['api_key' => true])
            ->assertNotFound();
        $this->withToken($token)
            ->postJson('/api/merchant/1/payment-requests', ['amount' => 100])
            ->assertNotFound();

        $this->getJson('/api/merchant/health')
            ->assertOk()
            ->assertJsonPath('domain', 'merchant');
    }

    private function createActiveCustomer(): Customer
    {
        return $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
    }
}
