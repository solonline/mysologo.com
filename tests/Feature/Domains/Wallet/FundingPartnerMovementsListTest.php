<?php

namespace Tests\Feature\Domains\Wallet;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Models\FundingPartnerMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class FundingPartnerMovementsListTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_customer_can_list_funding_partner_movements(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_list');

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => 'wal_list',
            'direction' => 'in',
            'type' => FundingPartnerMovementType::AccountTransfer->value,
            'amount' => 1500,
            'currency' => 'EUR',
            'original_amount' => 1280,
            'original_currency' => 'GBP',
            'fees_amount' => 9,
            'status' => 'completed',
            'source' => 'api',
            'idempotency_key' => 'list:test:1',
        ]);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/wallet/funding-accounts/'.$account->id.'/movements')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.amount', 1500)
            ->assertJsonPath('data.0.direction', 'in')
            ->assertJsonPath('data.0.original_amount', 1280)
            ->assertJsonPath('data.0.original_currency', 'GBP')
            ->assertJsonPath('data.0.fees_amount', 9);
    }

    private function createActiveCustomer()
    {
        return $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
    }
}
