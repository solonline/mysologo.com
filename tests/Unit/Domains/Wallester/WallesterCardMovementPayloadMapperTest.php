<?php

namespace Tests\Unit\Domains\Wallester;

use App\Domains\Wallester\Services\WallesterCardMovementPayloadMapper;
use App\Domains\Wallester\Services\WallesterFundingTransferDriver;
use Mockery;
use Tests\TestCase;

class WallesterCardMovementPayloadMapperTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_detects_foreign_exchange_fee_webhook(): void
    {
        $mapper = $this->mapper();

        $this->assertTrue($mapper->isFxFeeEvent([
            'wallester_event_type' => 'fee',
            'transaction_type' => 'authorizationforeignexchangepercentagefee',
            'type' => 'AuthorizationForeignExchangePercentageFee',
        ]));

        $this->assertTrue($mapper->isFxFeeEvent([
            'event' => 'create',
            'type' => 'fee',
            'data' => ['type' => 'AuthorizationForeignExchangePercentageFee'],
        ]));

        $this->assertFalse($mapper->isFxFeeEvent([
            'wallester_event_type' => 'authorization',
            'transaction_type' => 'paywavelowvaluepurchase',
            'type' => 'payWaveLowValuePurchase',
        ]));

        $this->assertFalse($mapper->isFxFeeEvent([
            'event' => 'create',
            'type' => 'fee',
            'data' => ['type' => 'AuthorizationATMWithdrawalEEAComplexFee'],
        ]));
        $this->assertTrue($mapper->isAtmWithdrawalFee([
            'event' => 'create',
            'type' => 'fee',
            'data' => ['type' => 'AuthorizationATMWithdrawalEEAComplexFee'],
        ]));
        $this->assertTrue($mapper->isAtmWithdrawal([
            'event' => 'notification',
            'type' => 'authorization',
            'data' => ['type' => 'ATMWithdrawal', 'group' => 'Withdraw'],
        ]));
        $this->assertFalse($mapper->isAtmWithdrawal([
            'event' => 'create',
            'type' => 'fee',
            'data' => ['type' => 'AuthorizationATMWithdrawalEEAComplexFee'],
        ]));
    }

    public function test_maps_quoted_amount_without_wallester_fee(): void
    {
        $amounts = $this->mapper()->mapAmounts([
            'account_amount' => -4.54,
            'account_currency_code' => 'EUR',
            'transaction_amount' => -3.88,
            'transaction_currency_code' => 'GBP',
            'fees_amount' => 0.09,
        ]);

        $this->assertSame(454, $amounts['amount_cents']);
        $this->assertSame('EUR', $amounts['currency']);
        $this->assertSame(9, $amounts['fees_amount_cents']);
        $this->assertSame(388, $amounts['original_amount_cents']);
        $this->assertSame('GBP', $amounts['original_currency']);
    }

    public function test_same_currency_without_fee_leaves_fees_empty(): void
    {
        $amounts = $this->mapper()->mapAmounts([
            'account_amount' => -12.34,
            'account_currency_code' => 'EUR',
            'transaction_amount' => -12.34,
            'transaction_currency_code' => 'EUR',
        ]);

        $this->assertSame(1234, $amounts['amount_cents']);
        $this->assertNull($amounts['fees_amount_cents']);
        $this->assertSame(1234, $amounts['original_amount_cents']);
        $this->assertSame('EUR', $amounts['original_currency']);
    }

    private function mapper(): WallesterCardMovementPayloadMapper
    {
        $driver = Mockery::mock(WallesterFundingTransferDriver::class);
        $driver->shouldReceive('absoluteAmountToCents')
            ->andReturnUsing(fn (mixed $amount): int => abs((int) round(((float) $amount) * 100)));

        return new WallesterCardMovementPayloadMapper($driver);
    }
}
