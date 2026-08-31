<?php

namespace Tests\Feature\Domains\Stripe;

use App\Domains\Stripe\Services\PurgeMerchantPaymentsService;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Domains\Wallet\Services\LedgerService;
use App\Domains\Wallet\Services\PlatformRevenueService;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\LedgerEntry;
use App\Models\Merchant;
use App\Models\MerchantPaymentRequest;
use App\Models\StripePaymentIntent;
use App\Models\StripeTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class PurgeMerchantPaymentsTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_dry_run_lists_candidates_without_writes(): void
    {
        [$keep, $purge] = $this->seedKeepAndPurgeTransactions();

        $result = app(PurgeMerchantPaymentsService::class)->execute(
            exceptId: $keep->id,
            dryRun: true,
        );

        $this->assertTrue($result['dry_run']);
        $this->assertSame([$purge->id], array_column($result['candidates'], 'id'));
        $this->assertSame(0, $result['purged']);

        $this->assertDatabaseHas('stripe_transactions', [
            'id' => $purge->id,
            'deleted_at' => null,
        ]);
    }

    public function test_purge_reverses_pending_and_platform_fee_then_soft_deletes(): void
    {
        [$keep, $purge] = $this->seedKeepAndPurgeTransactions();

        $merchantCustomer = Customer::query()->findOrFail($purge->customer_id);
        $platform = Customer::query()->where('type', 'platform')->firstOrFail();

        $result = app(PurgeMerchantPaymentsService::class)->execute(
            exceptId: $keep->id,
            dryRun: false,
        );

        $this->assertFalse($result['dry_run']);
        $this->assertSame(1, $result['purged']);
        $this->assertSame(402, $result['reversed_pending_cents']);
        $this->assertSame(2, $result['reversed_fees_cents']);

        $this->assertSoftDeleted('stripe_transactions', ['id' => $purge->id]);
        $this->assertDatabaseHas('stripe_transactions', [
            'id' => $keep->id,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'reference_id' => $purge->id,
            'entry_type' => LedgerEntryType::MANUAL_DEBIT->value,
            'description' => 'Stripe pending payment purge #'.$purge->id,
            'amount' => 402,
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'reference_id' => $purge->id,
            'customer_id' => $platform->id,
            'entry_type' => LedgerEntryType::MANUAL_DEBIT->value,
            'description' => 'Platform fee reversal #'.$purge->id,
            'amount' => 2,
        ]);

        $merchantBalance = CustomerBalance::query()
            ->where('customer_id', $merchantCustomer->id)
            ->where('currency', 'EUR')
            ->firstOrFail();

        $this->assertSame(80, (int) $merchantBalance->pending);
        $this->assertSame(0, (int) $merchantBalance->available);

        $platformBalance = CustomerBalance::query()
            ->where('customer_id', $platform->id)
            ->where('currency', 'EUR')
            ->firstOrFail();

        $this->assertSame(1, (int) $platformBalance->available);

        $this->assertDatabaseMissing('merchant_payment_requests', [
            'stripe_payment_intent_id' => $purge->stripe_payment_intent_id,
        ]);

        $this->assertDatabaseHas('stripe_payment_intents', [
            'stripe_payment_intent_id' => $purge->stripe_payment_intent_id,
            'stripe_transaction_id' => null,
        ]);

        $this->assertDatabaseHas('merchant_payment_requests', [
            'transaction_id' => $keep->id,
        ]);
    }

    public function test_cleanup_orphaned_payment_requests_removes_requests_for_trashed_transactions(): void
    {
        [$keep, $purge] = $this->seedKeepAndPurgeTransactions();

        $purgePi = $purge->stripe_payment_intent_id;

        MerchantPaymentRequest::query()
            ->where('transaction_id', $purge->id)
            ->update(['transaction_id' => null]);

        $purge->delete();

        $this->assertDatabaseHas('merchant_payment_requests', [
            'stripe_payment_intent_id' => $purgePi,
        ]);

        $result = app(PurgeMerchantPaymentsService::class)->cleanupOrphanedPaymentRequests();

        $this->assertSame(1, $result['deleted_requests']);
        $this->assertDatabaseMissing('merchant_payment_requests', [
            'stripe_payment_intent_id' => $purgePi,
        ]);
        $this->assertDatabaseHas('merchant_payment_requests', [
            'transaction_id' => $keep->id,
        ]);
    }

    public function test_artisan_command_requires_dry_run_or_force(): void
    {
        $this->artisan('stripe:purge-merchant-payments', ['--except' => 30])
            ->assertFailed();
    }

    public function test_artisan_dry_run_succeeds(): void
    {
        [$keep] = $this->seedKeepAndPurgeTransactions();

        $this->artisan('stripe:purge-merchant-payments', [
            '--except' => $keep->id,
            '--dry-run' => true,
        ])->assertSuccessful();
    }

    /**
     * @return array{0: StripeTransaction, 1: StripeTransaction}
     */
    private function seedKeepAndPurgeTransactions(): array
    {
        $platform = Customer::query()->create([
            'name' => 'Yoop Platform',
            'email' => 'platform-purge@yoop.com',
            'username' => 'platform_purge',
            'type' => 'platform',
            'currency' => 'EUR',
            'status' => 'ACTIVE',
            'email_verified_at' => now(),
        ]);

        $retail = $this->createRetailCustomer([
            'name' => 'Merchant Purge Customer',
            'currency' => 'EUR',
        ]);

        $merchant = Merchant::query()->create([
            'customer_id' => $retail->id,
            'name' => 'Loja Purge',
            'slug' => 'loja-purge-'.uniqid(),
            'api_key' => hash('sha256', 'yoop_purge'),
            'mode' => 'test',
            'is_active' => true,
        ]);

        $keep = $this->createMerchantPaymentBundle(
            customer: $retail,
            merchant: $merchant,
            amount: 110,
            net: 80,
            platformFee: 1,
            pi: 'pi_keep_purge',
            charge: 'ch_keep_purge',
            pending: false,
        );

        $purge = $this->createMerchantPaymentBundle(
            customer: $retail,
            merchant: $merchant,
            amount: 440,
            net: 402,
            platformFee: 2,
            pi: 'pi_purge_target',
            charge: 'ch_purge_target',
            pending: true,
        );

        app(LedgerService::class)->recordPendingCredit(
            customerId: $retail->id,
            currency: 'EUR',
            entryType: LedgerEntryType::PAYMENT_RECEIVED,
            amount: 80,
            reference: $keep,
            description: 'Stripe payment #'.$keep->id,
            metadata: ['source' => 'merchant_payment', 'settlement' => 'awaiting_stripe_payout'],
        );

        app(LedgerService::class)->record(
            customerId: $platform->id,
            currency: 'EUR',
            entryType: LedgerEntryType::MANUAL_CREDIT,
            amount: 1,
            reference: $keep,
            description: PlatformRevenueService::LEDGER_PLATFORM_FEE_DESCRIPTION_PREFIX.$keep->id,
        );

        app(LedgerService::class)->recordPendingCredit(
            customerId: $retail->id,
            currency: 'EUR',
            entryType: LedgerEntryType::PAYMENT_RECEIVED,
            amount: 402,
            reference: $purge,
            description: 'Stripe payment #'.$purge->id,
            metadata: ['source' => 'merchant_payment', 'settlement' => 'awaiting_stripe_payout'],
        );

        app(LedgerService::class)->record(
            customerId: $platform->id,
            currency: 'EUR',
            entryType: LedgerEntryType::MANUAL_CREDIT,
            amount: 2,
            reference: $purge,
            description: PlatformRevenueService::LEDGER_PLATFORM_FEE_DESCRIPTION_PREFIX.$purge->id,
        );

        return [$keep, $purge];
    }

    private function createMerchantPaymentBundle(
        Customer $customer,
        Merchant $merchant,
        int $amount,
        int $net,
        int $platformFee,
        string $pi,
        string $charge,
        bool $pending,
    ): StripeTransaction {
        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::MERCHANT_PAYMENT,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => $amount,
            'stripe_fee' => max(0, $amount - $net - $platformFee),
            'platform_fee' => $platformFee,
            'net_amount' => $net,
            'immediate_gross_amount' => 0,
            'wallester_funding_net_amount' => 0,
            'stripe_deferred_net_amount' => $pending ? $net : null,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'stripe_payment_intent_id' => $pi,
            'stripe_charge_id' => $charge,
            'partner_funding_status' => $pending
                ? PartnerFundingStatus::AwaitingStripePayout
                : PartnerFundingStatus::NotRequired,
        ]);

        MerchantPaymentRequest::query()->create([
            'merchant_id' => $merchant->id,
            'amount' => $amount,
            'currency' => 'EUR',
            'description' => 'Pedido '.$pi,
            'status' => 'completed',
            'stripe_payment_intent_id' => $pi,
            'transaction_id' => $transaction->id,
            'paid_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        StripePaymentIntent::query()->create([
            'customer_id' => $customer->id,
            'stripe_payment_intent_id' => $pi,
            'stripe_transaction_id' => $transaction->id,
            'amount' => $amount,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'type' => TransactionType::MERCHANT_PAYMENT->value,
            'purpose' => 'merchant_payment',
        ]);

        return $transaction;
    }
}
