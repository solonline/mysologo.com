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

        $this->assertCount(2, $timeline);
        $this->assertSame('wallester', $timeline->first()->source);
        $this->assertFalse($timeline->first()->isIncoming);
        $this->assertSame('DISTRICT', $timeline->first()->description);
        $this->assertTrue($timeline->last()->isIncoming);
        $this->assertSame('stripe', $timeline->last()->source);
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
        $this->assertSame('Crédito manual', $timeline->first()->typeLabel);
        $this->assertSame('Banco da Plataforma', $timeline->first()->sourceLabel);
        $this->assertSame('Crédito de compensação', $timeline->first()->description);
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
            'amount' => 463,
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
        $this->assertSame(463, $timeline->first()->amountCents);
        $this->assertSame('EUR', $timeline->first()->currency);
        $this->assertSame('3,88 GBP · câmbio 0,09 EUR', $timeline->first()->fxDetailLabel);
    }
}
