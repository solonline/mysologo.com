<?php

namespace App\Domains\Wallester\Services;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Contracts\WallesterAccountRepositoryInterface;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\CustomerWallesterAccount;
use App\Models\CustomerWallesterCard;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Services\FundingPartnerMovementService;
use App\Domains\Wallet\Services\LedgerService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WallesterService
{
    public function __construct(
        protected WallesterGatewayInterface $gateway,
        protected WallesterAccountRepositoryInterface $wallesterAccountRepository,
        protected Wallester3dsService $threeDsService,
        protected WallesterSettingsService $wallesterSettings,
        protected WallesterFundingTransferDriver $fundingTransferDriver,
        protected FundingPartnerMovementService $fundingMovementService,
        protected LedgerService $ledger,
    ) {}

    public function createAccount(array $data, string $currency = 'EUR'): array
    {
        return $this->gateway->forCurrency($currency)->createAccount($data);
    }

    public function showCardForCustomer(Customer $customer, CustomerWallesterCard $card): array
    {
        $this->ensureCustomerOwnsCard($customer, $card);
        $card->loadMissing('wallesterAccount');
        $currency = (string) ($card->wallesterAccount?->funding_currency ?? 'EUR');

        return $this->gateway->forCurrency($currency)->getCard($card->wallester_card_id, (string) $customer->id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createCardForCustomer(
        Customer $customer,
        array $data,
    ): CustomerWallesterCard {
        $account = $this->wallesterAccountRepository->findByCustomerId($customer->id);

        if ($account === null) {
            throw new ApiException(
                ErrorCode::ACCOUNT_NOT_ACTIVE,
                422,
                'Wallester account not found for this customer.',
            );
        }

        if ($account->status !== IntegrationAccountStatus::ACTIVE) {
            throw new ApiException(
                ErrorCode::ACCOUNT_NOT_ACTIVE,
                422,
                'Wallester account is not active.',
            );
        }

        $isAdditionalVirtual = $this->isAdditionalVirtualCard($account, $data);
        $feeCents = $this->wallesterSettings->virtualCardValueCents();

        if ($isAdditionalVirtual && $feeCents > 0) {
            $this->ensureAdditionalVirtualCardAffordable($customer, $account, $feeCents);
        }

        $card = $this->createCard(
            $this->buildCreateCardPayload($customer, $account, $data),
            (string) $customer->id,
        );

        if ($isAdditionalVirtual && $feeCents > 0) {
            $this->chargeAdditionalVirtualCardFee($customer, $account, $feeCents, $card);
        }

        if ($card->type === 'Virtual' && $card->status === 'Created') {
            return $this->activateVirtualCardForCustomer($customer, $card);
        }

        return $card;
    }

    /**
     * @return array{currency: string, virtual: array{fee_cents: int, will_charge: bool}, physical: array{fee_cents: int, will_charge: bool}}
     */
    public function issuanceQuoteForCustomer(Customer $customer): array
    {
        $account = $this->wallesterAccountRepository->findByCustomerId($customer->id);
        $currency = (string) ($account?->funding_currency ?? 'EUR');
        $hasOpenCard = $account !== null && $this->hasOpenCard($account);

        $virtualFeeCents = $this->wallesterSettings->virtualCardValueCents();
        $physicalFeeCents = 0;

        return [
            'currency' => $currency,
            'virtual' => [
                'fee_cents' => $virtualFeeCents,
                'will_charge' => $hasOpenCard && $virtualFeeCents > 0,
            ],
            'physical' => [
                'fee_cents' => $physicalFeeCents,
                'will_charge' => $hasOpenCard && $physicalFeeCents > 0,
            ],
        ];
    }

    public function activateVirtualCardForCustomer(Customer $customer, CustomerWallesterCard $card): CustomerWallesterCard
    {
        $this->ensureCustomerOwnsCard($customer, $card);
        $this->ensureVirtualCardActivatable($card);

        if ($card->status === 'Active') {
            return $card;
        }

        $card->loadMissing('wallesterAccount');

        $wallesterCard = $this->gateway
            ->forCurrency((string) ($card->wallesterAccount?->funding_currency ?? 'EUR'))
            ->activateCard($card->wallester_card_id, (string) $customer->id);

        if (! isset($wallesterCard['id'])) {
            throw new RuntimeException('Wallester card activation returned empty response.');
        }

        $card->update(CustomerWallesterCard::updatableAttributesFromWallesterResponse($wallesterCard));

        return $card->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createCard(array $data, ?string $auditUserId = null): CustomerWallesterCard
    {
        $wallesterAccountId = (string) ($data['account_id'] ?? '');

        if ($wallesterAccountId === '') {
            throw new RuntimeException('Wallester account_id is required to create a card.');
        }

        $account = $this->wallesterAccountRepository->findByWallesterAccountId($wallesterAccountId);

        if ($account === null) {
            throw new RuntimeException("Wallester account [{$wallesterAccountId}] was not found.");
        }

        $card = $this->gateway
            ->forCurrency((string) ($account->funding_currency ?? 'EUR'))
            ->createCard($data, $auditUserId);

        if (! isset($card['id'])) {
            throw new RuntimeException('Wallester card creation returned empty response.');
        }

        return DB::transaction(function () use ($account, $card): CustomerWallesterCard {
            $persistedCard = CustomerWallesterCard::query()->create(
                CustomerWallesterCard::attributesFromWallesterResponse($card, $account->id),
            );

            $account->increment('cards_count');

            return $persistedCard;
        });
    }

    public function blockCardForCustomer(Customer $customer, CustomerWallesterCard $card): CustomerWallesterCard
    {
        $this->ensureCustomerOwnsCard($customer, $card);

        if ($card->status === 'Blocked') {
            return $card;
        }

        $this->ensureCardBlockable($card);

        $card->loadMissing('wallesterAccount');

        $wallesterCard = $this->gateway
            ->forCurrency((string) ($card->wallesterAccount?->funding_currency ?? 'EUR'))
            ->blockCard($card->wallester_card_id, (string) $customer->id);

        if (! isset($wallesterCard['id'])) {
            throw new RuntimeException('Wallester card blocking returned empty response.');
        }

        $card->update(CustomerWallesterCard::updatableAttributesFromWallesterResponse($wallesterCard));

        return $card->fresh();
    }

    public function unblockCardForCustomer(Customer $customer, CustomerWallesterCard $card): CustomerWallesterCard
    {
        $this->ensureCustomerOwnsCard($customer, $card);

        if ($card->status === 'Active') {
            return $card;
        }

        $this->ensureCardUnblockable($card);

        $card->loadMissing('wallesterAccount');

        $wallesterCard = $this->gateway
            ->forCurrency((string) ($card->wallesterAccount?->funding_currency ?? 'EUR'))
            ->unblockCard($card->wallester_card_id, (string) $customer->id);

        if (! isset($wallesterCard['id'])) {
            throw new RuntimeException('Wallester card unblocking returned empty response.');
        }

        $card->update(CustomerWallesterCard::updatableAttributesFromWallesterResponse($wallesterCard));

        return $card->fresh();
    }

    public function closeCardForCustomer(Customer $customer, CustomerWallesterCard $card): CustomerWallesterCard
    {
        $this->ensureCustomerOwnsCard($customer, $card);

        if ($card->status === 'Closed') {
            return $card;
        }

        $this->ensureCardClosable($card);

        $card->loadMissing('wallesterAccount');

        $wallesterCard = $this->gateway
            ->forCurrency((string) ($card->wallesterAccount?->funding_currency ?? 'EUR'))
            ->closeCard($card->wallester_card_id, (string) $customer->id);

        $status = $wallesterCard['status'] ?? null;

        if (! isset($wallesterCard['id']) || ! in_array($status, ['Closed', 'Closing'], true)) {
            throw new RuntimeException('Wallester card closing did not confirm a closed card.');
        }

        $card->update(CustomerWallesterCard::updatableAttributesFromWallesterResponse($wallesterCard));

        return $card->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isAdditionalVirtualCard(CustomerWallesterAccount $account, array $data): bool
    {
        if (($data['type'] ?? null) !== 'Virtual') {
            return false;
        }

        return $this->hasOpenCard($account);
    }

    private function hasOpenCard(CustomerWallesterAccount $account): bool
    {
        return $account->cards()
            ->whereIn('status', CustomerWallesterCard::OPEN_STATUSES)
            ->exists();
    }

    private function ensureAdditionalVirtualCardAffordable(
        Customer $customer,
        CustomerWallesterAccount $account,
        int $feeCents,
    ): void {
        // Validação baseada no saldo “live” da Wallester (cêntimos),
        // não em balances/ledger local (que pode não estar pré-populado).
        $currency = (string) ($account->funding_currency ?? 'EUR');
        $availableCents = $this->fundingTransferDriver->getAvailableBalance($customer, $currency);
        if ($availableCents < $feeCents) {
            throw new ApiException(
                ErrorCode::INSUFFICIENT_BALANCE,
                422,
                'Balance is not enough to create an additional virtual card.',
            );
        }
    }

    private function chargeAdditionalVirtualCardFee(
        Customer $customer,
        CustomerWallesterAccount $account,
        int $feeCents,
        CustomerWallesterCard $createdCard,
    ): void {
        $currency = (string) ($account->funding_currency ?? 'EUR');

        $idempotencyKey = 'virtual-card-issuance-fee:'.$customer->id.':'.$createdCard->id;

        // Regista o movimento na plataforma e sincroniza as contas main/platform.
        $outMovement = $this->fundingMovementService->transferFromCustomerToPlatformFees(
            from: $customer,
            amountCents: $feeCents,
            currency: $currency,
            description: 'Taxa de emissão de cartão virtual',
            idempotencyKey: $idempotencyKey,
            movementType: FundingPartnerMovementType::VirtualCardIssuanceFee,
        );

        // Regista a saída no ledger interno (customer_balances) — idempotente.
        if ($outMovement !== null) {
            $this->ledger->record(
                customerId: (int) $customer->id,
                currency: $currency,
                entryType: LedgerEntryType::VIRTUAL_CARD_ISSUANCE_FEE,
                amount: $feeCents,
                reference: $outMovement,
                description: 'Taxa de emissão de cartão virtual',
            );
        }
    }

    private function ensureCardBlockable(CustomerWallesterCard $card): void
    {
        if ($card->status !== 'Active') {
            throw new ApiException(
                ErrorCode::CARD_NOT_BLOCKABLE,
                422,
                'This card cannot be blocked in its current status.',
            );
        }
    }

    private function ensureCardUnblockable(CustomerWallesterCard $card): void
    {
        if ($card->status !== 'Blocked') {
            throw new ApiException(
                ErrorCode::CARD_NOT_UNBLOCKABLE,
                422,
                'This card cannot be unblocked in its current status.',
            );
        }
    }

    private function ensureCardClosable(CustomerWallesterCard $card): void
    {
        if (! in_array($card->status, ['Active', 'Blocked'], true)) {
            throw new ApiException(
                ErrorCode::CARD_NOT_CLOSABLE,
                422,
                'This card cannot be closed in its current status.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildCreateCardPayload(Customer $customer, CustomerWallesterAccount $account, array $data): array
    {
        unset($data['account_id'], $data['3d_secure_settings'], $data['pin'], $data['device_token']);

        $security = is_array($data['security'] ?? null) ? $data['security'] : [];

        return array_merge($data, [
            'account_id' => $account->wallester_account_id,
            '3d_secure_settings' => $this->threeDsService->outOfBandSettingsFor($account, $customer),
            'security' => array_merge([
                'contactless_enabled' => true,
                'withdrawal_enabled' => false,
            ], $security, [
                'internet_purchase_enabled' => true,
            ]),
        ]);
    }

    private function ensureCustomerOwnsCard(Customer $customer, CustomerWallesterCard $card): void
    {
        $card->loadMissing('wallesterAccount');

        if ($card->wallesterAccount?->customer_id !== $customer->id) {
            throw new ApiException(
                ErrorCode::INVALID_CREDENTIALS,
                403,
                'This card does not belong to the authenticated customer.',
            );
        }
    }

    private function ensureVirtualCardActivatable(CustomerWallesterCard $card): void
    {
        if ($card->type !== 'Virtual') {
            throw new ApiException(
                ErrorCode::ACCOUNT_NOT_ACTIVE,
                422,
                'Only virtual cards can be activated through this endpoint.',
            );
        }

        if ($card->status !== 'Created' && $card->status !== 'Active') {
            throw new ApiException(
                ErrorCode::ACCOUNT_NOT_ACTIVE,
                422,
                'This card cannot be activated in its current status.',
            );
        }
    }
}
