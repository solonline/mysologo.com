<?php

namespace Tests\Unit\Domains\Wallet;

use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Services\LedgerService;
use App\Domains\Wallet\Services\RailMirrorService;
use App\Models\CustomerBalance;
use App\Models\RailMirrorEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class RailMirrorServiceTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_credit_and_debit_allocation_are_idempotent(): void
    {
        $customer = $this->createRetailCustomer();
        $mirror = app(RailMirrorService::class);

        $mirror->creditAllocation(
            customerId: $customer->id,
            currency: 'EUR',
            amountCents: 1_000,
            idempotencyKey: 'credit:1',
            reason: 'test_credit',
        );

        $mirror->creditAllocation(
            customerId: $customer->id,
            currency: 'EUR',
            amountCents: 1_000,
            idempotencyKey: 'credit:1',
            reason: 'test_credit',
        );

        $this->assertSame(1_000, (int) CustomerBalance::query()->where('customer_id', $customer->id)->value('wise_allocated'));
        $this->assertSame(1, RailMirrorEvent::query()->count());

        $mirror->debitAllocation(
            customerId: $customer->id,
            currency: 'EUR',
            amountCents: 400,
            idempotencyKey: 'debit:1',
            reason: 'test_debit',
        );

        $mirror->debitAllocation(
            customerId: $customer->id,
            currency: 'EUR',
            amountCents: 400,
            idempotencyKey: 'debit:1',
            reason: 'test_debit',
        );

        $this->assertSame(600, (int) CustomerBalance::query()->where('customer_id', $customer->id)->value('wise_allocated'));
        $this->assertSame(2, RailMirrorEvent::query()->count());
    }

    public function test_reserve_for_wise_transfer_debits_ledger_and_allocation(): void
    {
        $customer = $this->createRetailCustomer();

        app(LedgerService::class)->record(
            customerId: $customer->id,
            currency: 'EUR',
            entryType: LedgerEntryType::MANUAL_CREDIT,
            amount: 2_500,
            description: 'seed',
        );

        app(RailMirrorService::class)->creditAllocation(
            customerId: $customer->id,
            currency: 'EUR',
            amountCents: 2_500,
            idempotencyKey: 'seed-alloc',
            reason: 'seed',
        );

        app(RailMirrorService::class)->reserveForWiseTransfer(
            customer: $customer,
            currency: 'EUR',
            amountCents: 1_000,
            idempotencyKey: 'wise_transfer:abc',
        );

        $balance = CustomerBalance::query()->where('customer_id', $customer->id)->first();

        $this->assertSame(1_500, (int) $balance->wallester_allocated);
        $this->assertSame(1_500, (int) $balance->wise_allocated);
    }

    public function test_p2p_reallocation_moves_allocation_between_customers(): void
    {
        $from = $this->createRetailCustomer(['email' => 'from-rail@yoop.test', 'username' => 'from_rail']);
        $to = $this->createRetailCustomer(['email' => 'to-rail@yoop.test', 'username' => 'to_rail']);
        $mirror = app(RailMirrorService::class);

        $mirror->creditAllocation($from->id, 'EUR', 800, 'from-seed', 'seed');

        $mirror->reallocateBetweenCustomers(
            fromCustomerId: $from->id,
            toCustomerId: $to->id,
            currency: 'EUR',
            amountCents: 300,
            idempotencyKey: 'p2p:1',
        );

        $this->assertSame(500, (int) CustomerBalance::query()->where('customer_id', $from->id)->value('wise_allocated'));
        $this->assertSame(300, (int) CustomerBalance::query()->where('customer_id', $to->id)->value('wise_allocated'));
    }
}
