<?php

namespace App\Domains\Onboarding\Services;

use App\Domains\Ebury\Contracts\EburyGatewayInterface;
use App\Domains\Ebury\Jobs\SimulateEburyDecisionJob;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Onboarding\Jobs\ProvisionFundingPartnersJob;
use App\Domains\Stripe\Contracts\StripeAccountRepositoryInterface;
use App\Domains\Stripe\DTOs\CreateStripeAccountDTO;
use App\Domains\Stripe\Services\StripeService;
use App\Domains\Wallester\Contracts\WallesterAccountRepositoryInterface;
use App\Domains\Wallester\Services\WallesterService;
use App\Models\Customer;
use App\Models\CustomerWallesterAccount;
use Illuminate\Support\Facades\Log;

/**
 * Provisiona contas financeiras: Ebury e Wallester (pós-KYC), Stripe (após parceiro aprovado).
 */
class FinancialProvisioningService
{
    public function __construct(
        protected StripeAccountRepositoryInterface $stripeAccountRepository,
        protected StripeService $stripeService,
        protected WallesterService $walletService,
        protected WallesterAccountRepositoryInterface $wallesterAccountRepository,
        protected EburyGatewayInterface $eburyGateway,
        protected IntegrationAccountService $integrationAccounts,
    ) {}

    public function dispatchPostKycPartners(Customer $customer): void
    {
        ProvisionFundingPartnersJob::dispatch($customer->id)->afterCommit();
    }

    public function provisionPostKycPartners(Customer $customer): void
    {
        if (! $this->provisionWallester($customer)) {
            throw new \RuntimeException('Wallester provisioning failed.');
        }

        // $this->provisionEbury($customer); disabled until Ebury is ready
    }

    public function handleProvisioningExhausted(Customer $customer, string $reason): void
    {
        $customer = $customer->fresh('wallesterAccounts');
        $account = $customer->wallesterAccounts;

        if ($account !== null && $this->normalizeStatus($account->status) !== IntegrationAccountStatus::ACTIVE) {
            $this->integrationAccounts->updateWallester(
                $account,
                IntegrationAccountStatus::FAILED,
                $reason,
                'onboarding.provisioning',
            );
        }

        Log::channel('wallester')->error('Funding partner provisioning exhausted retries', [
            'customer_id' => $customer->id,
            'error' => $reason,
        ]);
    }

    public function provisionEbury(Customer $customer): bool
    {
        return $this->provisionEburyAccount($customer);
    }

    public function provisionWallester(Customer $customer): bool
    {
        return $this->provisionWallesterAccount($customer);
    }

    public function provisionStripe(Customer $customer): bool
    {
        return $this->provisionStripeAccount($customer);
    }

    private function provisionEburyAccount(Customer $customer): bool
    {
        $customer = $customer->fresh('eburyAccount');

        if ($this->isEburyAlreadySubmitted($customer)) {
            $status = $this->normalizeStatus($customer->eburyAccount?->status);

            return $status !== IntegrationAccountStatus::FAILED;
        }

        try {
            $account = $this->integrationAccounts->ensureEburyAccount($customer);
            $result = $this->eburyGateway->submitCustomer($customer, $account);

            if (! empty($result['account_id'])) {
                $account->ebury_account_id = $result['account_id'];
                $account->save();
            }

            $this->integrationAccounts->applyEburyExternalStatus(
                $account->fresh(),
                $result['status'] ?? 'in_review',
                'Dossiê submetido à Ebury.',
                'onboarding.provisioning',
            );

            $this->scheduleSimulatedEburyDecision($customer);

            return true;
        } catch (\Throwable $e) {
            Log::error('Ebury provisioning failed', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            if ($customer->eburyAccount) {
                $this->integrationAccounts->updateEbury(
                    $customer->eburyAccount,
                    IntegrationAccountStatus::FAILED,
                    $e->getMessage(),
                    'ebury.api',
                );
            }

            return false;
        }
    }

    private function provisionStripeAccount(Customer $customer): bool
    {
        if ($customer->stripeAccount) {
            return $this->normalizeStatus($customer->stripeAccount->status) === IntegrationAccountStatus::ACTIVE;
        }

        try {
            $stripeCustomer = $this->stripeService->createCustomer($customer);
            $account = $this->stripeAccountRepository->create(new CreateStripeAccountDTO(
                $customer->id,
                $stripeCustomer['id'],
            ));
            $this->integrationAccounts->updateStripe($account, IntegrationAccountStatus::ACTIVE, null, 'onboarding.provisioning');

            return true;
        } catch (\Throwable $e) {
            Log::error('Stripe provisioning failed', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            if ($customer->stripeAccount) {
                $this->integrationAccounts->updateStripe(
                    $customer->stripeAccount,
                    IntegrationAccountStatus::FAILED,
                    $e->getMessage(),
                    'stripe.api',
                );
            }

            return false;
        }
    }

    private function provisionWallesterAccount(Customer $customer): bool
    {
        $customer = $customer->fresh('wallesterAccounts');
        $account = $customer->wallesterAccounts;

        if ($account !== null && $this->normalizeStatus($account->status) === IntegrationAccountStatus::ACTIVE) {
            return true;
        }

        if ($account !== null && ! $this->isWallesterPendingSubmission($account)) {
            return $this->normalizeStatus($account->status) !== IntegrationAccountStatus::FAILED;
        }

        $account = $this->integrationAccounts->ensureWallesterAccount($customer);

        if ($this->normalizeStatus($account->status) === IntegrationAccountStatus::FAILED) {
            $this->integrationAccounts->updateWallester(
                $account,
                IntegrationAccountStatus::PENDING,
                'A tentar criar conta Wallester novamente.',
                'onboarding.retry',
            );
        }

        try {
            $wallesterAccount = $this->walletService->createAccount([
                'name' => $customer->name,
            ]);

            if (! $wallesterAccount || ! isset($wallesterAccount['id'])) {
                throw new \RuntimeException('Wallester account creation returned empty response.');
            }

            $suffix = (string) $customer->id;

            $account->wallester_account_id = $wallesterAccount['id'];
            $account->currency_code = $wallesterAccount['currency_code'] ?? 'EUR_'.$suffix;
            $account->product_id = $wallesterAccount['product_id'] ?? 'prod_'.$suffix;
            $account->company_id = $wallesterAccount['company_id'] ?? 'comp_'.$suffix;
            $account->save();

            $this->integrationAccounts->updateWallester($account, IntegrationAccountStatus::ACTIVE, null, 'onboarding.provisioning');

            return true;
        } catch (\Throwable $e) {
            $this->integrationAccounts->updateWallester(
                $account->fresh(),
                IntegrationAccountStatus::FAILED,
                $e->getMessage(),
                'wallester.api',
            );

            return false;
        }
    }

    private function isWallesterPendingSubmission(CustomerWallesterAccount $account): bool
    {
        return str_starts_with((string) $account->wallester_account_id, 'pending:');
    }

    private function isEburyAlreadySubmitted(Customer $customer): bool
    {
        $account = $customer->eburyAccount;

        if ($account === null) {
            return false;
        }

        return ! str_starts_with((string) $account->ebury_account_id, 'pending:');
    }

    private function scheduleSimulatedEburyDecision(Customer $customer): void
    {
        if (! config('services.ebury.simulate', true) || ! config('services.ebury.simulate_auto_approve', true)) {
            return;
        }

        $delay = (int) config('services.ebury.simulate_auto_approve_delay', 3);

        if (config('services.ebury.simulate_use_queue', false)) {
            SimulateEburyDecisionJob::dispatch($customer->id, 'approved')
                ->delay(now()->addSeconds($delay))
                ->onQueue('default');

            return;
        }

        dispatch_sync(new SimulateEburyDecisionJob($customer->id, 'approved'));
    }

    private function normalizeStatus(mixed $status): ?IntegrationAccountStatus
    {
        if ($status === null) {
            return null;
        }

        if ($status instanceof IntegrationAccountStatus) {
            return $status;
        }

        return IntegrationAccountStatus::fromLegacy((string) $status);
    }
}
