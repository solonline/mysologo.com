<?php

namespace Tests\Unit\Domains\Wallet;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TopupSettlementRoute;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\StripeTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class TopupSettlementRouteTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_resolves_wallester_immediate_route_from_allocation_fields(): void
    {
        $transaction = StripeTransaction::query()->create([
            'customer_id' => $this->createRetailCustomer()->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 5_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 5_000,
            'immediate_gross_amount' => 5_000,
            'wallester_funding_net_amount' => 5_000,
            'stripe_deferred_net_amount' => 0,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'partner_funding_status' => PartnerFundingStatus::Pending,
        ]);

        $route = TopupSettlementRoute::resolve($transaction);

        $this->assertSame(TopupSettlementRoute::WallesterImmediate, $route);
        $this->assertSame('Wallester imediato', $route->label());
        $this->assertSame('Transferência pendente', $route->detail($transaction));
        $this->assertFalse($route->showAvailableOn($transaction));
    }

    public function test_resolves_stripe_deferred_route_and_shows_available_on(): void
    {
        $transaction = StripeTransaction::query()->create([
            'customer_id' => $this->createRetailCustomer()->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 15_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 15_000,
            'immediate_gross_amount' => 0,
            'wallester_funding_net_amount' => 0,
            'stripe_deferred_net_amount' => 15_000,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'partner_funding_status' => PartnerFundingStatus::NotRequired,
            'available_on' => now()->addDays(3)->toDateString(),
        ]);

        $route = TopupSettlementRoute::resolve($transaction);

        $this->assertSame(TopupSettlementRoute::StripeDeferred, $route);
        $this->assertSame('Settlement Stripe', $route->label());
        $this->assertStringContainsString('Disp. em', $route->detail($transaction));
        $this->assertTrue($route->showAvailableOn($transaction));
    }

    public function test_completed_wallester_route_shows_funded_detail(): void
    {
        $fundedAt = now()->subHour();

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $this->createRetailCustomer()->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 5_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 5_000,
            'immediate_gross_amount' => 5_000,
            'wallester_funding_net_amount' => 5_000,
            'stripe_deferred_net_amount' => 0,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'partner_funding_status' => PartnerFundingStatus::Completed,
            'partner_funded_at' => $fundedAt,
        ]);

        $route = TopupSettlementRoute::resolve($transaction);

        $this->assertSame(TopupSettlementRoute::WallesterImmediate, $route);
        $this->assertStringContainsString('Fundado', $route->detail($transaction));
    }

    public function test_non_topup_returns_null_route(): void
    {
        $transaction = StripeTransaction::query()->create([
            'customer_id' => $this->createRetailCustomer()->id,
            'type' => TransactionType::P2P,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 1_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 1_000,
            'currency' => 'EUR',
            'status' => 'succeeded',
        ]);

        $this->assertNull(TopupSettlementRoute::resolve($transaction));
    }
}
