<?php

namespace Tests\Unit\Domains\Backoffice;

use App\Domains\Backoffice\Queries\GetCustomerActivityTimelineQuery;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
use App\Domains\Wallet\Enums\FundingPartnerMovementSource;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\FundingPartnerMovement;
use App\Models\StripeTransaction;
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
}
