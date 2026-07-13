<?php

namespace Tests\Unit\Domains\Stripe;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Stripe\Contracts\StripeGatewayInterface;
use App\Domains\Stripe\Enums\StripeFundingStatus;
use App\Domains\Stripe\Services\StripeService;
use App\Exceptions\ApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class StripeFundingInstructionsTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_create_customer_funding_instructions_persists_active_funding(): void
    {
        $customer = $this->createRetailCustomer();
        $this->createStripeAccount($customer);

        $this->mock(StripeGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('createCustomerFundingInstructions')
                ->once()
                ->andReturn([
                    'status' => 'success',
                    'message' => null,
                    'data' => $this->sampleStripeFundingInstructionsPayload(),
                ]);
        });

        $payload = app(StripeService::class)->createCustomerFundingInstructions($customer->fresh());

        $this->assertSame('bank_transfer', $payload['funding_type']);

        $account = $customer->fresh()->stripeAccount;
        $this->assertSame(StripeFundingStatus::ACTIVE, $account->funding_status);
        $this->assertSame('DE89370400440532013000', $account->funding_iban);
    }

    public function test_create_customer_funding_instructions_marks_failed_on_gateway_error(): void
    {
        $customer = $this->createRetailCustomer();
        $this->createStripeAccount($customer);

        $this->mock(StripeGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('createCustomerFundingInstructions')
                ->once()
                ->andReturn([
                    'status' => 'failed',
                    'message' => 'Stripe rejected funding',
                    'data' => null,
                ]);
        });

        try {
            app(StripeService::class)->createCustomerFundingInstructions($customer->fresh());
            $this->fail('Expected ApiException was not thrown.');
        } catch (ApiException $exception) {
            $this->assertSame(502, $exception->status);
        }

        $account = $customer->fresh()->stripeAccount;
        $this->assertSame(StripeFundingStatus::FAILED, $account->funding_status);
        $this->assertSame('Stripe rejected funding', $account->funding_status_reason);
    }

    public function test_get_funding_instructions_returns_persisted_data(): void
    {
        $customer = $this->createRetailCustomer();
        $this->createStripeAccount($customer, IntegrationAccountStatus::ACTIVE, null, [
            'funding_status' => StripeFundingStatus::ACTIVE,
            'funding_iban' => 'DE89370400440532013000',
            'funding_bic' => 'COBADEFFXXX',
            'funding_type' => 'bank_transfer',
            'bank_transfer_type' => 'eu_bank_transfer',
        ]);

        $data = app(StripeService::class)->getFundingInstructions($customer->fresh());

        $this->assertSame('DE89370400440532013000', $data['funding_iban']);
        $this->assertSame(StripeFundingStatus::ACTIVE, $data['funding_status']);
    }
}
