<?php

namespace Tests\Feature\Domains\Stripe;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Stripe\Contracts\StripeGatewayInterface;
use App\Domains\Stripe\Enums\StripeFundingStatus;
use App\Models\Customer;
use App\Models\CustomerStripeAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class ReprovisionStripeAccountsCommandTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_force_recreates_active_customer_with_iban_and_drops_incomplete_onboarding(): void
    {
        $active = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
        $this->createStripeAccount($active, IntegrationAccountStatus::ACTIVE, 'cus_old_active', [
            'funding_iban' => 'DE00000000000000000000',
            'viban' => 'DE00000000000000000000',
            'funding_status' => StripeFundingStatus::ACTIVE,
        ]);

        $pending = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::PROVISIONING,
        ]);
        $this->createStripeAccount($pending, IntegrationAccountStatus::ACTIVE, 'cus_old_pending');

        $trashed = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::SUSPENDED,
        ]);
        $trashedAccount = $this->createStripeAccount($trashed, IntegrationAccountStatus::ACTIVE, 'cus_old_trashed');
        $trashedAccount->delete();

        $platform = Customer::factory()->create([
            'type' => 'platform',
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email' => 'platform-reprovision@yoop.test',
        ]);
        $this->createStripeAccount($platform, IntegrationAccountStatus::ACTIVE, 'cus_old_platform');

        $this->mock(StripeGatewayInterface::class, function ($mock) use ($active) {
            $mock->shouldReceive('createCustomer')
                ->once()
                ->withArgs(function (array $payload) use ($active) {
                    return ($payload['metadata']['customer_id'] ?? null) === $active->id;
                })
                ->andReturn([
                    'status' => 'success',
                    'message' => null,
                    'data' => ['id' => 'cus_new_active'],
                ]);
            $mock->shouldReceive('createCustomerFundingInstructions')
                ->once()
                ->with('cus_new_active', \Mockery::type('array'))
                ->andReturn([
                    'status' => 'success',
                    'message' => null,
                    'data' => $this->sampleStripeFundingInstructionsPayload(),
                ]);
        });

        $this->artisan('stripe:reprovision-accounts', ['--force' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Contas criadas: 1. IBANs criados: 1. Falhas: 0.');

        $this->assertDatabaseMissing('customer_stripe_accounts', [
            'stripe_customer_id' => 'cus_old_active',
        ]);
        $this->assertDatabaseMissing('customer_stripe_accounts', [
            'stripe_customer_id' => 'cus_old_pending',
        ]);
        $this->assertDatabaseMissing('customer_stripe_accounts', [
            'stripe_customer_id' => 'cus_old_trashed',
        ]);
        $this->assertDatabaseMissing('customer_stripe_accounts', [
            'stripe_customer_id' => 'cus_old_platform',
        ]);

        $account = $active->fresh()->stripeAccount;
        $this->assertNotNull($account);
        $this->assertSame('cus_new_active', $account->stripe_customer_id);
        $this->assertSame(IntegrationAccountStatus::ACTIVE, $account->status);
        $this->assertSame(StripeFundingStatus::ACTIVE, $account->funding_status);
        $this->assertSame('DE89370400440532013000', $account->funding_iban);
        $this->assertSame('DE89370400440532013000', $account->viban);

        $this->assertNull($pending->fresh()->stripeAccount);
        $this->assertNull($trashed->fresh()->stripeAccount);
        $this->assertNull($platform->fresh()->stripeAccount);
        $this->assertSame(0, CustomerStripeAccount::withTrashed()->where('stripe_customer_id', 'cus_old_trashed')->count());
    }

    public function test_dry_run_does_not_delete_accounts_or_call_stripe(): void
    {
        $active = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
        ]);
        $this->createStripeAccount($active, IntegrationAccountStatus::ACTIVE, 'cus_keep');

        $this->mock(StripeGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('createCustomer')->never();
            $mock->shouldReceive('createCustomerFundingInstructions')->never();
        });

        $this->artisan('stripe:reprovision-accounts', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Dry-run');

        $this->assertDatabaseHas('customer_stripe_accounts', [
            'customer_id' => $active->id,
            'stripe_customer_id' => 'cus_keep',
        ]);
    }

    public function test_refuses_to_run_without_dry_run_or_force(): void
    {
        $this->artisan('stripe:reprovision-accounts')
            ->assertFailed();
    }

    public function test_funding_only_requests_funding_instructions_without_replacing_the_customer(): void
    {
        $active = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
        ]);
        $this->createStripeAccount($active, IntegrationAccountStatus::ACTIVE, 'cus_keep_funding', [
            'funding_status' => StripeFundingStatus::FAILED,
            'funding_status_reason' => 'Bank Transfer payment method',
        ]);

        $this->mock(StripeGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('createCustomer')->never();
            $mock->shouldReceive('createCustomerFundingInstructions')
                ->once()
                ->with('cus_keep_funding', \Mockery::type('array'))
                ->andReturn([
                    'status' => 'success',
                    'message' => null,
                    'data' => $this->sampleStripeFundingInstructionsPayload(),
                ]);
        });

        $this->artisan('stripe:reprovision-accounts', ['--funding-only' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('IBANs criados: 1.');

        $account = $active->fresh()->stripeAccount;
        $this->assertNotNull($account);
        $this->assertSame('cus_keep_funding', $account->stripe_customer_id);
        $this->assertSame('DE89370400440532013000', $account->funding_iban);
        $this->assertSame('DE89370400440532013000', $account->viban);
        $this->assertSame(StripeFundingStatus::ACTIVE, $account->funding_status);
    }
}
