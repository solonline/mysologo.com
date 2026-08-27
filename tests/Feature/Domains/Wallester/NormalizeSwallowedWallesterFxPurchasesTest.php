<?php

namespace Tests\Feature\Domains\Wallester;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Onboarding\Enums\IntegrationProvider;
use App\Domains\Wallester\Services\NormalizeSwallowedWallesterFxPurchasesService;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
use App\Domains\Wallet\Enums\FundingPartnerMovementSource;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\TransactionDirection;
use App\Models\CustomerBalance;
use App\Models\CustomerWallesterCard;
use App\Models\FundingPartnerMovement;
use App\Models\LedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class NormalizeSwallowedWallesterFxPurchasesTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_command_unifies_fee_row_that_swallowed_the_purchase(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_swallowed_fx');

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_swallowed_fx',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'available' => 44406,
            'pending' => 0,
        ]);

        $movement = FundingPartnerMovement::query()->create([
            'provider' => IntegrationProvider::WALLESTER,
            'funding_account_type' => $account->getMorphClass(),
            'funding_account_id' => $account->id,
            'external_account_id' => $account->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::Out,
            'type' => FundingPartnerMovementType::CardPurchase,
            'amount' => 9,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Webhook,
            'idempotency_key' => 'card:2c4a1e9f-3145-4c00-bbf4-71adfd0ed934:out',
            'partner_external_id' => '2c4a1e9f-3145-4c00-bbf4-71adfd0ed934',
            'metadata' => [
                'id' => '2c4a1e9f-3145-4c00-bbf4-71adfd0ed934',
                'type' => 'AuthorizationForeignExchangePercentageFee',
                'amount' => -0.09,
                'currency_code' => 'EUR',
                'wallester_event_type' => 'fee',
                'transaction_type' => 'authorizationforeignexchangepercentagefee',
                'authorization_id' => '0aa86719-5a62-4ce7-abf5-577d2918db20',
                'settlement_partner_external_id' => '0aa86719-5a62-4ce7-abf5-577d2918db20',
                'settlement_metadata' => [
                    'id' => '0aa86719-5a62-4ce7-abf5-577d2918db20',
                    'type' => 'payWaveLowValuePurchase',
                    'merchant_name' => 'LidlGB',
                    'account_amount' => -4.54,
                    'account_currency_code' => 'EUR',
                    'transaction_amount' => -3.88,
                    'transaction_currency_code' => 'GBP',
                    'fees_amount' => 0.09,
                    'wallester_event_type' => 'authorization',
                ],
            ],
        ]);

        LedgerEntry::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'direction' => TransactionDirection::Debit,
            'amount' => 9,
            'available_before' => 44415,
            'available_after' => 44406,
            'pending_before' => 0,
            'pending_after' => 0,
            'balance_after' => 44406,
            'entry_type' => LedgerEntryType::CARD_PURCHASE,
            'reference_type' => $movement->getMorphClass(),
            'reference_id' => $movement->id,
            'description' => null,
        ]);

        $this->artisan('wallester:normalize-fx-fee-purchases', ['--customer' => $customer->id])
            ->assertSuccessful();

        $this->assertDatabaseHas('funding_partner_movements', [
            'id' => $movement->id,
            'amount' => 463,
            'currency' => 'EUR',
            'original_amount' => 388,
            'original_currency' => 'GBP',
            'fees_amount' => 9,
            'partner_external_id' => '0aa86719-5a62-4ce7-abf5-577d2918db20',
            'description' => 'LidlGB',
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $customer->id,
            'entry_type' => LedgerEntryType::CARD_PURCHASE->value,
            'amount' => 454,
            'reference_id' => $movement->id,
        ]);
        $this->assertDatabaseHas('customer_balances', [
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'available' => 43952,
        ]);
        $this->assertTrue(app(NormalizeSwallowedWallesterFxPurchasesService::class)->execute($customer->id)->isEmpty());
    }
}
