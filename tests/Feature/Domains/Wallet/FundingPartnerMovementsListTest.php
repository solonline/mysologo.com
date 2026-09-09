<?php

namespace Tests\Feature\Domains\Wallet;

use App\Domains\Backoffice\Enums\CustomerBalanceAdjustmentDirection;
use App\Domains\Backoffice\Enums\CustomerBalanceAdjustmentStatus;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\CustomerBalanceAdjustment;
use App\Models\FundingPartnerMovement;
use App\Models\StripeTransaction;
use App\Models\User;
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

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => 'wal_list',
            'direction' => 'out',
            'type' => FundingPartnerMovementType::PhysicalCardIssuanceFee->value,
            'amount' => 350,
            'currency' => 'EUR',
            'status' => 'completed',
            'source' => 'api',
            'idempotency_key' => 'physical-card-platform-fee:3:17:out',
            'description' => 'Taxa de emissão de cartão físico',
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => 'wal_list',
            'direction' => 'out',
            'type' => FundingPartnerMovementType::PhysicalCardIssuanceFee->value,
            'amount' => 850,
            'currency' => 'EUR',
            'status' => 'completed',
            'source' => 'api',
            'idempotency_key' => 'physical-card-issuance-fee:3:17:out',
            'description' => 'Taxa de emissão de cartão físico',
        ]);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/wallet/funding-accounts/'.$account->id.'/movements')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.amount', 850)
            ->assertJsonPath('data.0.type', 'physical_card_issuance_fee')
            ->assertJsonPath('data.1.amount', 14)
            ->assertJsonPath('data.1.type', 'fee')
            ->assertJsonPath('data.2.amount', 454)
            ->assertJsonPath('data.2.type', 'card_purchase')
            ->assertJsonPath('data.2.original_amount', 1280)
            ->assertJsonPath('data.2.original_currency', 'GBP')
            ->assertJsonPath('data.2.fees_amount', 9)
            ->assertJsonPath('data.2.platform_fees_amount', 5)
            ->assertJsonPath('data.2.type_label', 'Compra cartão');
    }

    public function test_platform_credit_is_labelled_credito_yoop_and_not_a_generic_transfer(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_manual_adj_api');
        $user = User::factory()->create();

        $adjustment = CustomerBalanceAdjustment::query()->create([
            'customer_id' => $customer->id,
            'direction' => CustomerBalanceAdjustmentDirection::Credit,
            'amount_cents' => 100,
            'currency' => 'EUR',
            'description' => 'x',
            'status' => CustomerBalanceAdjustmentStatus::Executed,
            'required_approvals' => 1,
            'requested_by' => $user->id,
            'idempotency_key' => 'manual_adj:api-credit',
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => 'wal_manual_adj_api',
            'direction' => 'in',
            'type' => FundingPartnerMovementType::AccountTransfer->value,
            'amount' => 100,
            'currency' => 'EUR',
            'status' => 'completed',
            'source' => 'api',
            'idempotency_key' => $adjustment->idempotency_key.':in',
            'description' => 'x',
            'reference_type' => $adjustment->getMorphClass(),
            'reference_id' => $adjustment->id,
        ]);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/wallet/funding-accounts/'.$account->id.'/movements')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'account_transfer')
            ->assertJsonPath('data.0.type_label', 'Crédito Yoop')
            ->assertJsonPath('data.0.source_label', 'Crédito Yoop')
            ->assertJsonPath('data.0.is_p2p', false)
            ->assertJsonPath('data.0.amount', 100);
    }

    public function test_p2p_movement_is_labelled_transferencia_entre_contas(): void
    {
        $recipient = $this->createActiveCustomer();
        $sender = $this->createActiveCustomer();
        $recipientAccount = $this->createWallesterAccount($recipient, IntegrationAccountStatus::ACTIVE, 'wal_p2p_api_in');
        $senderAccount = $this->createWallesterAccount($sender, IntegrationAccountStatus::ACTIVE, 'wal_p2p_api_out');

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $recipientAccount::class,
            'funding_account_id' => $recipientAccount->id,
            'external_account_id' => $recipientAccount->wallester_account_id,
            'counterparty_external_account_id' => $senderAccount->wallester_account_id,
            'direction' => 'in',
            'type' => FundingPartnerMovementType::AccountTransfer->value,
            'amount' => 250,
            'currency' => 'EUR',
            'status' => 'completed',
            'source' => 'api',
            'idempotency_key' => 'p2p:api-label:in',
            'description' => 'P2P transfer',
        ]);

        $token = $recipient->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/wallet/funding-accounts/'.$recipientAccount->id.'/movements')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type_label', 'Transferência entre contas')
            ->assertJsonPath('data.0.is_p2p', true)
            ->assertJsonPath('data.0.counterparty.username', $sender->username)
            ->assertJsonPath('data.0.counterparty.role', 'sender');
    }

    public function test_wallet_transactions_list_excludes_p2p_already_shown_as_wallester_movement(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_p2p_stripe_dup');

        StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::P2P,
            'payment_method_type' => 'p2p',
            'direction' => 'credit',
            'amount' => 100,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 100,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'description' => 'P2P transfer',
            'stripe_created_at' => now(),
        ]);

        StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 5_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 5_000,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'description' => 'Top-up',
            'stripe_created_at' => now()->subMinute(),
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => $account->wallester_account_id,
            'direction' => 'in',
            'type' => FundingPartnerMovementType::AccountTransfer->value,
            'amount' => 100,
            'currency' => 'EUR',
            'status' => 'completed',
            'source' => 'api',
            'idempotency_key' => 'p2p:api-no-dup:in',
            'description' => 'P2P transfer',
        ]);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/wallet/transactions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'topup')
            ->assertJsonPath('data.0.type_label', 'Carregamento');

        $this->withToken($token)
            ->getJson('/api/wallet/funding-accounts/'.$account->id.'/movements')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type_label', 'Transferência entre contas');
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
