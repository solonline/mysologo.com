<?php

namespace App\Domains\Onboarding\Services;

use App\Domains\Onboarding\DTOs\IntegrationAccountState;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Onboarding\Enums\IntegrationProvider;
use App\Domains\Stripe\Enums\StripeFundingStatus;
use App\Models\Customer;
use App\Models\CustomerEburyAccount;
use App\Models\CustomerStripeAccount;
use App\Models\CustomerWallesterAccount;
use Illuminate\Support\Facades\Log;

class IntegrationAccountService
{
    public function __construct(
        protected CustomerLifecycleService $lifecycle,
    ) {}

    public function mapExternalStatus(IntegrationProvider $provider, string $external): IntegrationAccountStatus
    {
        $normalized = strtolower(trim($external));

        return match ($provider) {
            IntegrationProvider::STRIPE,
            IntegrationProvider::WALLESTER,
            IntegrationProvider::EBURY => match ($normalized) {
                'active', 'enabled', 'open', 'approved' => IntegrationAccountStatus::ACTIVE,
                'restricted', 'limited', 'partial' => IntegrationAccountStatus::RESTRICTED,
                'suspended', 'disabled', 'inactive', 'blocked', 'frozen' => IntegrationAccountStatus::SUSPENDED,
                'closed', 'deleted', 'terminated' => IntegrationAccountStatus::CLOSED,
                'failed', 'rejected', 'error', 'declined' => IntegrationAccountStatus::FAILED,
                'pending', 'processing', 'not_started' => IntegrationAccountStatus::PENDING,
                default => IntegrationAccountStatus::tryFrom(strtoupper($external)) ?? IntegrationAccountStatus::PENDING,
            },
        };
    }

    public function updateStripe(
        CustomerStripeAccount $account,
        IntegrationAccountStatus $status,
        ?string $reason = null,
        ?string $source = null,
    ): CustomerStripeAccount {
        return $this->persistStatus($account, $status, IntegrationProvider::STRIPE, $reason, $source);
    }

    public function updateWallester(
        CustomerWallesterAccount $account,
        IntegrationAccountStatus $status,
        ?string $reason = null,
        ?string $source = null,
    ): CustomerWallesterAccount {
        return $this->persistStatus($account, $status, IntegrationProvider::WALLESTER, $reason, $source);
    }

    public function updateEbury(
        CustomerEburyAccount $account,
        IntegrationAccountStatus $status,
        ?string $reason = null,
        ?string $source = null,
    ): CustomerEburyAccount {
        return $this->persistStatus($account, $status, IntegrationProvider::EBURY, $reason, $source);
    }

    public function applyStripeExternalStatus(
        CustomerStripeAccount $account,
        string $externalStatus,
        ?string $reason = null,
        ?string $source = 'webhook',
    ): CustomerStripeAccount {
        $status = $this->mapExternalStatus(IntegrationProvider::STRIPE, $externalStatus);

        return $this->updateStripe($account, $status, $reason, $source);
    }

    public function applyWallesterExternalStatus(
        CustomerWallesterAccount $account,
        string $externalStatus,
        ?string $reason = null,
        ?string $source = 'webhook',
    ): CustomerWallesterAccount {
        $status = $this->mapExternalStatus(IntegrationProvider::WALLESTER, $externalStatus);

        return $this->updateWallester($account, $status, $reason, $source);
    }

    public function applyEburyExternalStatus(
        CustomerEburyAccount $account,
        string $externalStatus,
        ?string $reason = null,
        ?string $source = 'webhook',
    ): CustomerEburyAccount {
        $normalized = strtolower(trim($externalStatus));

        if (in_array($normalized, ['in_review', 'under_review', 'reviewing', 'analysis'], true)) {
            $account = $this->updateEbury($account, IntegrationAccountStatus::PENDING, $reason, $source);
            $this->lifecycle->markEburyUnderReview($account->customer, $reason);

            return $account;
        }

        $status = $this->mapExternalStatus(IntegrationProvider::EBURY, $externalStatus);

        return $this->updateEbury($account, $status, $reason, $source);
    }

    public function ensureEburyAccount(Customer $customer): CustomerEburyAccount
    {
        return CustomerEburyAccount::firstOrCreate(
            ['customer_id' => $customer->id],
            [
                'ebury_account_id' => 'pending:'.$customer->id,
                'currency' => strtoupper($customer->currency ?? 'EUR'),
                'status' => IntegrationAccountStatus::PENDING,
                'status_reason' => 'Aguardando submissão dos documentos Ebury.',
                'status_source' => 'yoop.onboarding',
                'status_changed_at' => now(),
            ],
        );
    }

    public function ensureWallesterAccount(Customer $customer): CustomerWallesterAccount
    {
        $suffix = (string) $customer->id;

        return CustomerWallesterAccount::firstOrCreate(
            ['customer_id' => $customer->id],
            [
                'wallester_account_id' => 'pending:'.$customer->id,
                'currency_code' => 'EUR_'.$suffix,
                'funding_currency' => 'EUR',
                'product_id' => 'prod_'.$suffix,
                'company_id' => 'comp_'.$suffix,
                'status' => IntegrationAccountStatus::PENDING,
                'status_reason' => 'Aguardando criação da conta Wallester.',
                'status_source' => 'yoop.onboarding',
                'status_changed_at' => now(),
            ],
        );
    }

    public function findEburyByExternalId(string $eburyAccountId): ?CustomerEburyAccount
    {
        return CustomerEburyAccount::where('ebury_account_id', $eburyAccountId)->first();
    }

    public function findStripeByExternalId(string $stripeCustomerId): ?CustomerStripeAccount
    {
        return CustomerStripeAccount::where('stripe_customer_id', $stripeCustomerId)->first();
    }

    public function findWallesterByExternalId(string $wallesterAccountId): ?CustomerWallesterAccount
    {
        return CustomerWallesterAccount::where('wallester_account_id', $wallesterAccountId)->first();
    }

    public function handleStripeWebhook(string $eventType, array $object): void
    {
        match ($eventType) {
            'customer.cash_balance_settings.updated' => $this->handleStripeCashBalanceSettingsUpdated($object),
            default => null,
        };
    }

    public function canUsePlatform(Customer $customer): bool
    {
        return $this->lifecycle->canUsePlatform($customer);
    }

    public function canTransact(Customer $customer): bool
    {
        return $this->lifecycle->canTransact($customer);
    }

    public function canTopUpStripe(Customer $customer): bool
    {
        return $this->lifecycle->canTransact($customer)
            && $this->normalizeStatus($customer->stripeAccount?->status)?->allowsDeposits() === true;
    }

    public function canWithdrawWallester(Customer $customer): bool
    {
        return $this->lifecycle->canTransact($customer)
            && $this->normalizeStatus($customer->wallesterAccount?->status)?->allowsWithdrawals() === true;
    }

    public function canReceiveEbury(Customer $customer): bool
    {
        return $this->lifecycle->canTransact($customer)
            && $this->normalizeStatus($customer->eburyAccount?->status)?->allowsDeposits() === true;
    }

    /** @return array<string, mixed> */
    public function resolveCapabilities(Customer $customer): array
    {
        $integrationStates = $this->resolveStates($customer);

        return [
            'can_use_platform' => $this->canUsePlatform($customer),
            'can_transact' => $this->canTransact($customer),
            'can_top_up_stripe' => $this->canTopUpStripe($customer),
            'can_withdraw_wallester' => $this->canWithdrawWallester($customer),
            'can_receive_ebury' => $this->canReceiveEbury($customer),
            'blockers' => $this->resolveBlockers($customer, $integrationStates),
        ];
    }

    /** @return array<string, IntegrationAccountState> */
    public function resolveStates(Customer $customer): array
    {
        return [
            IntegrationProvider::STRIPE->value => $this->stateFromAccount(
                IntegrationProvider::STRIPE,
                $customer->stripeAccount,
            ),
            IntegrationProvider::WALLESTER->value => $this->stateFromAccount(
                IntegrationProvider::WALLESTER,
                $customer->wallesterAccount,
            ),
            IntegrationProvider::EBURY->value => $this->stateFromAccount(
                IntegrationProvider::EBURY,
                $customer->eburyAccount,
            ),
        ];
    }

    public function handleStripeFundingInstructionsCreated(CustomerStripeAccount $account, array $fundingInstructions): CustomerStripeAccount
    {
        $bankTransfer = $fundingInstructions['bank_transfer'] ?? [];
        $financialAddresses = $bankTransfer['financial_addresses'] ?? [];
        $ibanAddress = $this->resolveIbanFinancialAddress($financialAddresses);
        $ibanDetails = is_array($ibanAddress['iban'] ?? null) ? $ibanAddress['iban'] : [];

        $fundingIban = $ibanDetails['iban'] ?? null;

        $account->funding_type = $fundingInstructions['funding_type'] ?? null;
        $account->bank_transfer_type = $bankTransfer['type'] ?? null;
        $account->funding_iban = $fundingIban;
        $account->funding_bic = $ibanDetails['bic'] ?? null;
        $account->funding_account_holder_name = $ibanDetails['account_holder_name'] ?? null;
        $account->funding_country = $ibanDetails['country'] ?? null;
        $account->funding_financial_addresses = $financialAddresses;
        $account->viban = $fundingIban;
        $account->funding_status = StripeFundingStatus::ACTIVE;
        $account->funding_status_reason = null;
        $account->funding_status_changed_at = now();
        $account->save();

        return $account;
    }

    public function markStripeFundingFailed(CustomerStripeAccount $account, string $reason): CustomerStripeAccount
    {
        $account->funding_status = StripeFundingStatus::FAILED;
        $account->funding_status_reason = $reason;
        $account->funding_status_changed_at = now();
        $account->save();

        return $account;
    }

    /** @param list<array<string, mixed>> $financialAddresses */
    private function resolveIbanFinancialAddress(array $financialAddresses): array
    {
        foreach ($financialAddresses as $address) {
            if (($address['type'] ?? null) === 'iban') {
                return $address;
            }
        }

        return $financialAddresses[0] ?? [];
    }

    private function handleStripeCashBalanceSettingsUpdated(array $object): void
    {
        $stripeCustomerId = $object['customer'] ?? null;
        $reconciliationMode = $object['reconciliation_mode'] ?? null;

        if (! $stripeCustomerId) {
            return;
        }

        $account = $this->findStripeByExternalId($stripeCustomerId);

        if (! $account) {
            Log::channel('stripe')->warning('Stripe webhook: conta não encontrada para cash_balance_settings', [
                'stripe_customer_id' => $stripeCustomerId,
            ]);

            return;
        }

        if ($reconciliationMode === 'disabled' || ($object['restrictions'] ?? null)) {
            $this->applyStripeExternalStatus(
                $account,
                'restricted',
                'Cash balance restrictions applied by Stripe.',
                'stripe.webhook',
            );
        }
    }

    /** @param array<string, IntegrationAccountState> $integrationStates */
    private function resolveBlockers(Customer $customer, array $integrationStates): array
    {
        $blockers = [];
        $lifecycle = $this->lifecycle->current($customer);

        if ($lifecycle->isNegative() || $lifecycle->isGloballyBlocked()) {
            $blockers[] = [
                'scope' => 'platform',
                'reason' => $customer->status_reason ?? $lifecycle->label(),
                'source' => 'yoop',
            ];
        } elseif (! $this->lifecycle->canUsePlatform($customer)) {
            $blockers[] = [
                'scope' => 'platform',
                'reason' => $lifecycle->label(),
                'source' => 'yoop',
            ];
        }

        if (! $this->lifecycle->canTransact($customer) && ! $lifecycle->isNegative()) {
            $blockers[] = [
                'scope' => 'transactions',
                'reason' => $customer->status_reason ?? 'Account not fully activated.',
                'source' => 'yoop',
            ];
        }

        $ebury = $integrationStates[IntegrationProvider::EBURY->value] ?? null;

        if ($ebury && $ebury->provider === IntegrationProvider::EBURY && $lifecycle === CustomerLifecycleStatus::ACTIVE) {
            if ($ebury->status !== IntegrationAccountStatus::ACTIVE && $ebury->status !== null) {
                $blockers[] = [
                    'scope' => 'ebury',
                    'reason' => $ebury->reason ?? 'Transferências Ebury indisponíveis.',
                    'source' => $ebury->source ?? 'ebury',
                    'blocked_actions' => ['receive_transfers', 'fx'],
                ];
            }
        }

        foreach ($integrationStates as $state) {
            if ($state->provider === IntegrationProvider::EBURY) {
                continue;
            }

            if ($state->status === null || $state->status === IntegrationAccountStatus::ACTIVE) {
                continue;
            }

            if ($state->status->isBlocked() || $state->status === IntegrationAccountStatus::RESTRICTED) {
                $blockers[] = [
                    'scope' => $state->provider->value,
                    'reason' => $state->reason ?? $state->status->value,
                    'source' => $state->source ?? $state->provider->value,
                    'blocked_actions' => $state->blockedActions,
                ];
            }
        }

        return $blockers;
    }

    private function stateFromAccount(
        IntegrationProvider $provider,
        CustomerStripeAccount|CustomerWallesterAccount|CustomerEburyAccount|null $account,
    ): IntegrationAccountState {
        if ($account === null) {
            return new IntegrationAccountState(
                provider: $provider,
                status: null,
                reason: $provider === IntegrationProvider::EBURY
                    ? 'Conta Ebury ainda não submetida.'
                    : 'Account not provisioned.',
            );
        }

        $status = $this->normalizeStatus($account->status);

        return new IntegrationAccountState(
            provider: $provider,
            status: $status,
            reason: $account->status_reason,
            source: $account->status_source,
            updatedAt: $account->status_changed_at?->toIso8601String(),
            blockedActions: $status ? $status->blockedActionsFor($provider) : [],
        );
    }

    private function persistStatus(
        CustomerStripeAccount|CustomerWallesterAccount|CustomerEburyAccount $account,
        IntegrationAccountStatus $status,
        IntegrationProvider $provider,
        ?string $reason,
        ?string $source,
    ): CustomerStripeAccount|CustomerWallesterAccount|CustomerEburyAccount {
        $previous = $this->normalizeStatus($account->status);

        $account->status = $status;
        $account->status_reason = $reason;
        $account->status_source = $source;
        $account->status_changed_at = now();
        $account->save();

        if ($previous !== $status) {
            Log::info('Integration account status changed', [
                'provider' => $provider->value,
                'customer_id' => $account->customer_id,
                'from' => $previous?->value,
                'to' => $status->value,
                'reason' => $reason,
                'source' => $source,
            ]);
        }

        if ($provider === IntegrationProvider::EBURY && $account instanceof CustomerEburyAccount) {
            $this->lifecycle->syncFromEburyAccount($account);
        }

        if ($provider === IntegrationProvider::WALLESTER && $account instanceof CustomerWallesterAccount) {
            $this->lifecycle->syncFromWallesterAccount($account);
        }

        return $account;
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
