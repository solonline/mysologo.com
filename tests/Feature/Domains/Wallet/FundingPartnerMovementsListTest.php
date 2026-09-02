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
            'direction' => 'out',
            'type' => FundingPartnerMovementType::CardPurchase->value,
            'amount' => 454,
            'currency' => 'EUR',
            'original_amount' => 1280,
            'original_currency' => 'GBP',
            'fees_amount' => 9,
            'platform_fees_amount' => 5,
            'status' => 'completed',
            'source' => 'webhook',
            'idempotency_key' => 'card:list-fx:out',
            'partner_external_id' => 'list-fx',
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => 'wal_list',
            'direction' => 'out',
            'type' => FundingPartnerMovementType::Fee->value,
            'amount' => 14,
            'currency' => 'EUR',
            'fees_amount' => 9,
            'platform_fees_amount' => 5,
            'status' => 'completed',
            'source' => 'webhook',
            'idempotency_key' => 'fx-fees:list-fx',
            'description' => 'Câmbio',
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => 'wal_list',
            'direction' => 'out',
            'type' => FundingPartnerMovementType::AccountTransfer->value,
            'amount' => 5,
            'currency' => 'EUR',
            'status' => 'completed',
            'source' => 'api',
            'idempotency_key' => 'fx-platform:list-fx:out',
            'description' => 'Platform FX fee list-fx',
        ]);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/wallet/funding-accounts/'.$account->id.'/movements')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.amount', 14)
            ->assertJsonPath('data.0.type', 'fee')
            ->assertJsonPath('data.1.amount', 454)
            ->assertJsonPath('data.1.type', 'card_purchase')
            ->assertJsonPath('data.1.original_amount', 1280)
            ->assertJsonPath('data.1.original_currency', 'GBP')
            ->assertJsonPath('data.1.fees_amount', 9)
            ->assertJsonPath('data.1.platform_fees_amount', 5);
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
