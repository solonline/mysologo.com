<?php

namespace Tests\Unit\Domains\Backoffice;

use App\Domains\Backoffice\Enums\CustomerBalanceAdjustmentDirection;
use App\Domains\Backoffice\Enums\CustomerBalanceAdjustmentStatus;
use App\Domains\Backoffice\Queries\GetCustomerActivityTimelineQuery;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
use App\Domains\Wallet\Enums\FundingPartnerMovementSource;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\Customer;
use App\Models\CustomerBalanceAdjustment;
use App\Models\FundingPartnerMovement;
use App\Models\StripeTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class GetCustomerActivityTimelineQueryTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_timeline_merges_stripe_topup_and_wallester_card_purchase(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_timeline');

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
            'stripe_created_at' => now()->subHour(),
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => $account->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::Out,
            'type' => FundingPartnerMovementType::CardPurchase,
            'amount' => 90,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Webhook,
            'idempotency_key' => 'card:timeline-purchase:out',
            'partner_external_id' => 'timeline-purchase',
            'description' => 'DISTRICT',
            'metadata' => [
                'merchant_city' => 'SETUBAL',
                'merchant_category_code' => '7339',
            ],
            'created_at' => now(),
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => $account->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::In,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => 5_000,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => 'timeline-transfer:in',
            'reference_type' => StripeTransaction::class,
            'reference_id' => 1,
        ]);

        $timeline = app(GetCustomerActivityTimelineQuery::class)->execute($customer);

        $this->assertCount(3, $timeline);
        $this->assertSame('stripe', $timeline->last()->source);
        $this->assertTrue($timeline->last()->isIncoming);

        $wallester = $timeline->filter(fn ($item) => $item->source === 'wallester');
        $this->assertCount(2, $wallester);
        $this->assertTrue($wallester->contains(fn ($item) => $item->description === 'DISTRICT' && ! $item->isIncoming));
        $this->assertTrue($wallester->contains(fn ($item) => $item->isIncoming && $item->amountCents === 5_000));
    }

    public function test_timeline_hides_stripe_topup_funding_and_keeps_gross_available_and_fee(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_timeline_topup');

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'bank_transfer',
            'direction' => 'credit',
            'amount' => 300,
            'stripe_fee' => 2,
            'platform_fee' => 0,
            'net_amount' => 298,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'description' => 'Bank transfer top-up via VIBAN',
            'stripe_created_at' => now(),
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => $account->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::In,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => 298,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => 'stripe_topup:'.$transaction->id.':in',
            'description' => 'Stripe top-up #'.$transaction->id,
            'reference_type' => $transaction->getMorphClass(),
            'reference_id' => $transaction->id,
        ]);

        $timeline = app(GetCustomerActivityTimelineQuery::class)->execute($customer);

        $this->assertCount(1, $timeline);
        $this->assertSame('stripe', $timeline->first()->source);
        $this->assertSame(300, $timeline->first()->amountCents);
        $this->assertSame(298, $timeline->first()->netAmountCents);
        $this->assertSame(2, $timeline->first()->feeCents);
    }

    public function test_timeline_includes_same_customer_visible_wallester_types_as_api(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_timeline_api');

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => $account->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::In,
            'type' => FundingPartnerMovementType::InboundPayment,
            'amount' => 1_200,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Webhook,
            'idempotency_key' => 'inbound:timeline-api',
            'description' => 'Entrada Wallester',
            'created_at' => now(),
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => $account->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::Out,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => 5,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => 'fx-platform:timeline-api:out',
            'description' => 'Platform FX fee timeline-api',
            'created_at' => now()->subSecond(),
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => $account->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::Out,
            'type' => FundingPartnerMovementType::PhysicalCardIssuanceFee,
            'amount' => 350,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => 'physical-card-platform-fee:timeline-api:out',
            'description' => 'Taxa plataforma cartão físico',
            'created_at' => now()->subSeconds(2),
        ]);

        $timeline = app(GetCustomerActivityTimelineQuery::class)->execute($customer);

        $this->assertCount(1, $timeline);
        $this->assertSame('wallester', $timeline->first()->source);
        $this->assertTrue($timeline->first()->isIncoming);
        $this->assertSame('Entrada', $timeline->first()->typeLabel);
        $this->assertSame('Entrada Wallester', $timeline->first()->description);
    }

    public function test_timeline_includes_executed_platform_balance_adjustments(): void
    {
        $customer = $this->createRetailCustomer();
        $user = User::factory()->create();

        CustomerBalanceAdjustment::query()->create([
            'customer_id' => $customer->id,
            'direction' => CustomerBalanceAdjustmentDirection::Credit,
            'amount_cents' => 3_000,
            'currency' => 'EUR',
            'description' => 'Crédito de compensação',
            'status' => CustomerBalanceAdjustmentStatus::Executed,
            'required_approvals' => 1,
            'requested_by' => $user->id,
            'idempotency_key' => 'manual_adj:timeline-credit',
            'partner_external_id' => 'pay_timeline_credit',
            'updated_at' => now(),
        ]);

        $timeline = app(GetCustomerActivityTimelineQuery::class)->execute($customer);

        $this->assertCount(1, $timeline);
        $this->assertSame('platform', $timeline->first()->source);
        $this->assertTrue($timeline->first()->isIncoming);
        $this->assertSame('Crédito Yoop', $timeline->first()->typeLabel);
        $this->assertSame('Crédito Yoop', $timeline->first()->sourceLabel);
        $this->assertSame('Crédito de compensação', $timeline->first()->description);
    }

    public function test_timeline_does_not_duplicate_platform_adjustment_as_wallester_transfer(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_manual_adj');
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
            'idempotency_key' => 'manual_adj:timeline-no-dup',
            'partner_external_id' => 'pay_timeline_no_dup',
            'updated_at' => now(),
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => $account->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::In,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => 100,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => $adjustment->idempotency_key.':in',
            'partner_external_id' => 'pay_timeline_no_dup',
            'description' => 'x',
            'reference_type' => $adjustment->getMorphClass(),
            'reference_id' => $adjustment->id,
            'created_at' => now()->subSecond(),
        ]);

        $timeline = app(GetCustomerActivityTimelineQuery::class)->execute($customer);

        $this->assertCount(1, $timeline);
        $this->assertSame('platform', $timeline->first()->source);
        $this->assertTrue($timeline->first()->isIncoming);
        $this->assertSame('Crédito Yoop', $timeline->first()->typeLabel);
        $this->assertSame('Crédito Yoop', $timeline->first()->sourceLabel);
        $this->assertSame('x', $timeline->first()->description);
        $this->assertNull($timeline->first()->wallesterMovement);
    }

    public function test_platform_timeline_keeps_wallester_leg_of_retail_manual_credit(): void
    {
        $retail = $this->createRetailCustomer();
        $user = User::factory()->create();
        $platform = Customer::factory()->create(['type' => 'platform', 'name' => 'Yoop Platform']);
        $master = $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, 'wal_master_manual_adj', [
            'is_main' => true,
        ]);

        $adjustment = CustomerBalanceAdjustment::query()->create([
            'customer_id' => $retail->id,
            'direction' => CustomerBalanceAdjustmentDirection::Credit,
            'amount_cents' => 100,
            'currency' => 'EUR',
            'description' => 'x',
            'status' => CustomerBalanceAdjustmentStatus::Executed,
            'required_approvals' => 1,
            'requested_by' => $user->id,
            'idempotency_key' => 'manual_adj:timeline-platform-leg',
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $master::class,
            'funding_account_id' => $master->id,
            'external_account_id' => $master->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::Out,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => 100,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => $adjustment->idempotency_key.':out',
            'description' => 'x',
            'reference_type' => $adjustment->getMorphClass(),
            'reference_id' => $adjustment->id,
        ]);

        $timeline = app(GetCustomerActivityTimelineQuery::class)->execute($platform);

        $this->assertCount(1, $timeline);
        $this->assertSame('wallester', $timeline->first()->source);
        $this->assertFalse($timeline->first()->isIncoming);
        $this->assertSame(100, $timeline->first()->amountCents);
        $this->assertSame('Débito Yoop', $timeline->first()->typeLabel);
    }

    public function test_timeline_includes_fx_detail_for_foreign_card_purchase(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_timeline_fx');

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => $account->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::Out,
            'type' => FundingPartnerMovementType::CardPurchase,
            'amount' => 454,
            'currency' => 'EUR',
            'original_amount' => 388,
            'original_currency' => 'GBP',
            'fees_amount' => 9,
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Webhook,
            'idempotency_key' => 'card:timeline-fx:out',
            'partner_external_id' => 'timeline-fx',
            'description' => 'LidlGB',
        ]);

        $timeline = app(GetCustomerActivityTimelineQuery::class)->execute($customer);

        $this->assertCount(1, $timeline);
        $this->assertSame(454, $timeline->first()->amountCents);
        $this->assertSame('EUR', $timeline->first()->currency);
        $this->assertSame('3,88 GBP → 4,54 EUR', $timeline->first()->fxDetailLabel);
    }

    public function test_platform_timeline_includes_incoming_yoop_fx_fee(): void
    {
        $retail = $this->createRetailCustomer();
        $retailAccount = $this->createWallesterAccount($retail, IntegrationAccountStatus::ACTIVE, 'wal_retail_fx');
        $platform = Customer::factory()->create(['type' => 'platform', 'name' => 'Yoop Platform']);
        $master = $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, 'wal_master_fx', [
            'is_main' => true,
        ]);

        $purchase = FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $retailAccount::class,
            'funding_account_id' => $retailAccount->id,
            'external_account_id' => $retailAccount->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::Out,
            'type' => FundingPartnerMovementType::CardPurchase,
            'amount' => 454,
            'currency' => 'EUR',
            'fees_amount' => 9,
            'platform_fees_amount' => 5,
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Webhook,
            'idempotency_key' => 'card:timeline-master-fx:out',
            'partner_external_id' => 'timeline-master-fx',
            'description' => 'LidlGB',
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $master::class,
            'funding_account_id' => $master->id,
            'external_account_id' => $master->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::In,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => 5,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => 'fx-platform:timeline-master-fx:in',
            'description' => 'Platform FX fee timeline-master-fx',
            'reference_type' => $purchase->getMorphClass(),
            'reference_id' => $purchase->id,
        ]);

        $timeline = app(GetCustomerActivityTimelineQuery::class)->execute($platform);

        $this->assertCount(1, $timeline);
        $this->assertTrue($timeline->first()->isIncoming);
        $this->assertSame(5, $timeline->first()->amountCents);
        $this->assertSame('Câmbio Yoop', $timeline->first()->typeLabel);
        $this->assertSame('Câmbio Yoop · LidlGB', $timeline->first()->description);
    }

    public function test_timeline_does_not_duplicate_p2p_as_stripe_and_wallester(): void
    {
        $recipient = $this->createRetailCustomer([
            'name' => 'Destinatario Timeline',
            'username' => 'dest_timeline',
        ]);
        $sender = $this->createRetailCustomer([
            'name' => 'Remetente Timeline',
            'username' => 'rem_timeline',
        ]);
        $recipientAccount = $this->createWallesterAccount($recipient, IntegrationAccountStatus::ACTIVE, 'wal_p2p_in');
        $senderAccount = $this->createWallesterAccount($sender, IntegrationAccountStatus::ACTIVE, 'wal_p2p_out');

        StripeTransaction::query()->create([
            'customer_id' => $recipient->id,
            'type' => TransactionType::P2P,
            'payment_method_type' => 'p2p',
            'direction' => 'credit',
            'amount' => 1,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 1,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'description' => 'P2P transfer',
            'stripe_created_at' => now(),
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $recipientAccount::class,
            'funding_account_id' => $recipientAccount->id,
            'external_account_id' => $recipientAccount->wallester_account_id,
            'counterparty_external_account_id' => $senderAccount->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::In,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => 1,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => 'p2p:timeline-no-dup:in',
            'description' => 'P2P transfer',
        ]);

        $timeline = app(GetCustomerActivityTimelineQuery::class)->execute($recipient);

        $this->assertCount(1, $timeline);
        $this->assertSame('wallester', $timeline->first()->source);
        $this->assertTrue($timeline->first()->isIncoming);
        $this->assertSame('Transferência entre contas', $timeline->first()->typeLabel);
        $this->assertSame('Remetente Timeline', $timeline->first()->p2pCounterpartyName);
        $this->assertSame('rem_timeline', $timeline->first()->p2pCounterpartyUsername);
        $this->assertSame('sender', $timeline->first()->p2pCounterpartyRole);
    }
}
