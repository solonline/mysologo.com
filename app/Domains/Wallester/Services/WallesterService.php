<?php

namespace App\Domains\Wallester\Services;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Contracts\WallesterAccountRepositoryInterface;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Settings\Services\SettingsService;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\CustomerWallesterAccount;
use App\Models\CustomerWallesterCard;
use App\Models\LedgerEntry;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
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
        protected SettingsService $settingsService,
        protected WallesterCardRsaKeyService $rsaKeys,
        protected WallesterSensitiveDataDecryptor $decryptor,
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

    public function getEncryptedPinForCustomer(Customer $customer, CustomerWallesterCard $card): string
    {
        $this->ensureCustomerOwnsCard($customer, $card);
        $this->ensureCardCanRevealSensitiveData($card);
        $card->loadMissing('wallesterAccount');
        $currency = (string) ($card->wallesterAccount?->funding_currency ?? 'EUR');
        $publicKey = $this->rsaKeys->publicKeyBase64($currency);

        $encryptedPin = $this->gateway->forCurrency($currency)->getEncryptedPinCard(
            $card->wallester_card_id,
            $publicKey,
            (string) $customer->id,
        );

        try {
            return $this->decryptor->decryptPin($encryptedPin, $currency);
        } catch (RuntimeException $exception) {
            throw new ApiException(
                ErrorCode::INTERNAL_ERROR,
                500,
                'Unable to decrypt Wallester card PIN.',
                previous: $exception,
            );
        }
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

        $isVirtual = ($data['type'] ?? null) === 'Virtual';
        $hasOpenCard = $this->hasOpenCard($account);
        $feeCents = $this->wallesterSettings->cardValueCents($isVirtual);
        $dispatch = null;

        if (! $isVirtual) {
            $data['delivery_address'] = $this->validatedCustomerDeliveryAddress($data);
            $dispatch = $this->resolveDispatchOption($account, 'PRT', (string) $customer->id);
        }

        $shouldChargeVirtual = $isVirtual && $hasOpenCard && $feeCents > 0;
        $shouldChargePhysical = ! $isVirtual && $feeCents > 0;
        $requiredCents = $isVirtual
            ? $feeCents
            : max($feeCents, (int) ($dispatch['price_cents'] ?? 0));

        if (($shouldChargeVirtual || $shouldChargePhysical) && $requiredCents > 0) {
            $this->ensureCardIssuanceAffordable($customer, $account, $requiredCents, $isVirtual);
        }

        $card = $this->createCard(
            $this->buildCreateCardPayload($customer, $account, $data, $isVirtual, $dispatch),
            (string) $customer->id,
        );

        if (! $isVirtual) {
            $this->storeCustomerDeliveryAddress($customer, $card, $data);
            $this->settlePhysicalCardIssuanceFee($customer, $card, $dispatch);
        } elseif ($shouldChargeVirtual) {
            $this->chargeAdditionalCardFee($customer, $account, $feeCents, $card, true);
        }

        if ($isVirtual && $card->status === 'Created') {
            return $this->activateVirtualCardForCustomer($customer, $card);
        }

        return $card;
    }

    public function linkCardToAnotherCustomer(Customer $customer, CustomerWallesterCard $card, Customer $newCustomer): CustomerWallesterCard
    {
        $this->ensureCustomerOwnsCard($customer, $card);
        $this->ensureCardLinkable($card);

        if ($customer->id === $newCustomer->id) {
            throw new ApiException(
                ErrorCode::CARD_NOT_LINKABLE,
                422,
                'Cannot link a card to the same customer.',
            );
        }

        $card->loadMissing('wallesterAccount');
        $sourceAccount = $card->wallesterAccount;
        $targetAccount = $this->wallesterAccountRepository->findByCustomerId($newCustomer->id);

        if ($targetAccount === null || $targetAccount->status !== IntegrationAccountStatus::ACTIVE) {
            throw new ApiException(
                ErrorCode::ACCOUNT_NOT_ACTIVE,
                422,
                'Wallester account not found or not active for the target customer.',
            );
        }

        $sourceCurrency = strtoupper((string) ($sourceAccount?->funding_currency ?? 'EUR'));
        $targetCurrency = strtoupper((string) ($targetAccount->funding_currency ?? 'EUR'));

        if ($sourceCurrency !== $targetCurrency) {
            throw new ApiException(
                ErrorCode::CARD_NOT_LINKABLE,
                422,
                'Target Wallester account currency does not match the card account.',
            );
        }

        $targetWallesterAccountId = (string) $targetAccount->wallester_account_id;

        if ($targetWallesterAccountId === '') {
            throw new ApiException(
                ErrorCode::ACCOUNT_NOT_ACTIVE,
                422,
                'Target Wallester account is missing an external account id.',
            );
        }

        if ($sourceAccount?->id === $targetAccount->id) {
            return $card;
        }

        $wallesterCard = $this->gateway
            ->forCurrency($sourceCurrency)
            ->linkCardToAccount(
                $card->wallester_card_id,
                $targetWallesterAccountId,
                (string) $customer->id,
            );

        if (! isset($wallesterCard['id'])) {
            throw new RuntimeException('Wallester card linking returned empty response.');
        }

        return DB::transaction(function () use ($card, $sourceAccount, $targetAccount, $wallesterCard): CustomerWallesterCard {
            $attributes = CustomerWallesterCard::updatableAttributesFromWallesterResponse($wallesterCard);
            $attributes['customer_wallester_account_id'] = $targetAccount->id;
            $card->update($attributes);

            if ($sourceAccount !== null && $sourceAccount->id !== $targetAccount->id) {
                $sourceAccount->decrement('cards_count');
                $targetAccount->increment('cards_count');
            }

            return $card->fresh();
        });
    }

    /**
     * @return array{currency: string, virtual: array{fee_cents: int, will_charge: bool}, physical: array{fee_cents: int, will_charge: bool}}
     */
    public function issuanceQuoteForCustomer(Customer $customer): array
    {
        $account = $this->wallesterAccountRepository->findByCustomerId($customer->id);
        $currency = (string) ($account?->funding_currency ?? 'EUR');
        $hasOpenCard = $account !== null && $this->hasOpenCard($account);

        $virtualFeeCents = $this->wallesterSettings->cardValueCents(true);
        $physicalFeeCents = $this->wallesterSettings->cardValueCents(false);

        return [
            'currency' => $currency,
            'virtual' => [
                'fee_cents' => $virtualFeeCents,
                'will_charge' => $hasOpenCard && $virtualFeeCents > 0,
            ],
            'physical' => [
                'fee_cents' => $physicalFeeCents,
                'will_charge' => $physicalFeeCents > 0,
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
     * Debita o valor das settings no ledger e transfere o restante (settings − envio Wallester) para a subconta master.
     *
     * @param  array{dispatch_method: string, price_cents: int, price?: float|null}|null  $dispatch
     */
    public function settlePhysicalCardIssuanceFee(
        Customer $customer,
        CustomerWallesterCard $card,
        ?array $dispatch = null,
    ): void {
        $this->ensureCustomerOwnsCard($customer, $card);
        $card->loadMissing('wallesterAccount');

        if ($card->type === 'Virtual') {
            return;
        }

        $account = $card->wallesterAccount;
        if ($account === null) {
            throw new RuntimeException('Wallester account not found for this card.');
        }

        $feeCents = $this->wallesterSettings->cardValueCents(false);
        if ($feeCents <= 0) {
            return;
        }

        $dispatch ??= $this->dispatchOptionForExistingCard($account, $card, (string) $customer->id);
        $dispatchCents = max(0, (int) ($dispatch['price_cents'] ?? 0));
        $remainderCents = max(0, $feeCents - $dispatchCents);
        $currency = (string) ($account->funding_currency ?? 'EUR');
        $metadata = [
            'card_id' => $card->id,
            'dispatch_method' => $dispatch['dispatch_method'] ?? null,
            'dispatch_cents' => $dispatchCents,
            'platform_fee_cents' => $remainderCents,
        ];

        if ($remainderCents > 0) {
            $this->fundingMovementService->transferFromCustomerToPlatformFees(
                from: $customer,
                amountCents: $remainderCents,
                currency: $currency,
                description: 'Taxa de emissão de cartão físico',
                idempotencyKey: 'physical-card-platform-fee:'.$customer->id.':'.$card->id,
                reference: $card,
                movementType: FundingPartnerMovementType::PhysicalCardIssuanceFee,
            );
        }

        $visibleMovement = $this->fundingMovementService->recordLocalCompletedMovement(
            account: $account,
            type: FundingPartnerMovementType::PhysicalCardIssuanceFee,
            direction: FundingPartnerMovementDirection::Out,
            amountCents: $feeCents,
            currency: $currency,
            idempotencyKey: 'physical-card-issuance-fee:'.$customer->id.':'.$card->id.':out',
            description: 'Taxa de emissão de cartão físico',
            card: $card,
            metadata: $metadata,
            reference: $card,
        );

        if ($this->hasPhysicalIssuanceLedger($customer, $card)) {
            return;
        }

        $this->ledger->record(
            customerId: (int) $customer->id,
            currency: $currency,
            entryType: LedgerEntryType::PHYSICAL_CARD_ISSUANCE_FEE,
            amount: $feeCents,
            reference: $visibleMovement,
            description: 'Taxa de emissão de cartão físico',
            metadata: $metadata,
        );
    }

    private function hasOpenCard(CustomerWallesterAccount $account): bool
    {
        return $account->cards()
            ->whereIn('status', CustomerWallesterCard::OPEN_STATUSES)
            ->exists();
    }

    private function ensureCardIssuanceAffordable(
        Customer $customer,
        CustomerWallesterAccount $account,
        int $feeCents,
        bool $isVirtual,
    ): void {
        // Validação baseada no saldo “live” da Wallester (cêntimos),
        // não em balances/ledger local (que pode não estar pré-populado).
        $currency = (string) ($account->funding_currency ?? 'EUR');
        $availableCents = $this->fundingTransferDriver->getAvailableBalance($customer, $currency);
        if ($availableCents < $feeCents) {
            throw new ApiException(
                ErrorCode::INSUFFICIENT_BALANCE,
                422,
                $isVirtual ? 'Balance is not enough to create an additional virtual card.' : 'Balance is not enough to create a physical card.',
            );
        }
    }

    private function chargeAdditionalCardFee(
        Customer $customer,
        CustomerWallesterAccount $account,
        int $feeCents,
        CustomerWallesterCard $createdCard,
        bool $isVirtual,
    ): void {
        $currency = (string) ($account->funding_currency ?? 'EUR');

        $idempotencyKey = ($isVirtual ? 'virtual' : 'physical').'-card-issuance-fee:'.$customer->id.':'.$createdCard->id;

        // Regista o movimento na plataforma e sincroniza as contas main/platform.
        $outMovement = $this->fundingMovementService->transferFromCustomerToPlatformFees(
            from: $customer,
            amountCents: $feeCents,
            currency: $currency,
            description: 'Taxa de emissão de cartão '.($isVirtual ? 'virtual' : 'físico'),
            idempotencyKey: $idempotencyKey,
            movementType: $isVirtual ? FundingPartnerMovementType::VirtualCardIssuanceFee : FundingPartnerMovementType::PhysicalCardIssuanceFee,
        );

        // Regista a saída no ledger interno (customer_balances) — idempotente.
        if ($outMovement !== null) {
            $this->ledger->record(
                customerId: (int) $customer->id,
                currency: $currency,
                entryType: $isVirtual ? LedgerEntryType::VIRTUAL_CARD_ISSUANCE_FEE : LedgerEntryType::PHYSICAL_CARD_ISSUANCE_FEE,
                amount: $feeCents,
                reference: $outMovement,
                description: 'Taxa de emissão de cartão '.($isVirtual ? 'virtual' : 'físico'),
            );
        }
    }

    private function hasPhysicalIssuanceLedger(Customer $customer, CustomerWallesterCard $card): bool
    {
        return LedgerEntry::query()
            ->where('customer_id', $customer->id)
            ->where('entry_type', LedgerEntryType::PHYSICAL_CARD_ISSUANCE_FEE)
            ->where(function ($query) use ($card): void {
                $query
                    ->where(function ($query) use ($card): void {
                        $query
                            ->where('reference_type', $card->getMorphClass())
                            ->where('reference_id', $card->id);
                    })
                    ->orWhere('metadata->card_id', $card->id);
            })
            ->exists();
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

    private function ensureCardLinkable(CustomerWallesterCard $card): void
    {
        if ($card->status === 'Closed') {
            throw new ApiException(
                ErrorCode::CARD_NOT_LINKABLE,
                422,
                'This card cannot be linked in its current status.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{dispatch_method: string, price_cents: int, price?: float|null}|null  $dispatch
     * @return array<string, mixed>
     */
    private function buildCreateCardPayload(
        Customer $customer,
        CustomerWallesterAccount $account,
        array $data,
        bool $isVirtual = true,
        ?array $dispatch = null,
    ): array {
        unset($data['account_id'], $data['3d_secure_settings'], $data['pin'], $data['device_token'], $data['delivery_address']);

        $security = is_array($data['security'] ?? null) ? $data['security'] : [];

        $deliveryAddress = null;
        if (! $isVirtual) {
            $countryCode = 'PRT';
            $dispatch ??= $this->resolveDispatchOption($account, $countryCode, (string) $customer->id);
            $deliveryAddress = [
                'address1' => $this->settingsService->getByKey('address.street'),
                'city' => $this->settingsService->getByKey('address.city'),
                'company_name' => 'TAMIETTO JUNIOR UNIPESSOAL LDA',
                'country_code' => $countryCode,
                'dispatch_method' => $dispatch['dispatch_method'],
                'first_name' => 'Carlos',
                'last_name' => 'Tamietto',
                'phone' => '+351926112844',
                'postal_code' => $this->settingsService->getByKey('address.postal_code'),
            ];
        }

        return array_merge($data, [
            'account_id' => $account->wallester_account_id,
            '3d_secure_settings' => $this->threeDsService->outOfBandSettingsFor($account, $customer),
            'delivery_address' => $deliveryAddress,
            'security' => array_merge([
                'contactless_enabled' => true,
                'withdrawal_enabled' => false,
            ], $security, [
                'internet_purchase_enabled' => true,
            ]),
            'type' => $isVirtual ? 'Virtual' : 'ChipAndPin',
        ]);
    }

    /**
     * @return array{dispatch_method: string, price_cents: int, price: float|null}
     */
    private function resolveDispatchOption(CustomerWallesterAccount $account, string $countryCode, string $auditUserId): array
    {
        $methods = $this->gateway
            ->forCurrency((string) ($account->funding_currency ?? 'EUR'))
            ->listCardDispatchMethods($countryCode, $auditUserId);

        if ($methods === []) {
            throw new RuntimeException("Wallester has no dispatch methods for country [{$countryCode}].");
        }

        usort($methods, function (array $left, array $right): int {
            $leftPrice = $left['price'];
            $rightPrice = $right['price'];

            if ($leftPrice === null && $rightPrice === null) {
                return 0;
            }

            if ($leftPrice === null) {
                return 1;
            }

            if ($rightPrice === null) {
                return -1;
            }

            return $leftPrice <=> $rightPrice;
        });

        $chosen = $methods[0];
        if ($chosen['price'] === null) {
            throw new RuntimeException("Wallester dispatch method [{$chosen['dispatch_method']}] has no price for country [{$countryCode}].");
        }

        return [
            'dispatch_method' => (string) $chosen['dispatch_method'],
            'price_cents' => $this->eurosToCents($chosen['price']),
            'price' => $chosen['price'],
        ];
    }

    /**
     * @return array{dispatch_method: string, price_cents: int, price: float|null}
     */
    private function dispatchOptionForExistingCard(
        CustomerWallesterAccount $account,
        CustomerWallesterCard $card,
        string $auditUserId,
    ): array {
        $delivery = is_array($card->delivery_address) ? $card->delivery_address : [];
        $method = trim((string) ($delivery['dispatch_method'] ?? ''));
        $countryCode = trim((string) ($delivery['country_code'] ?? 'PRT'));

        if ($method === '') {
            throw new RuntimeException("Physical card [{$card->id}] is missing a dispatch method.");
        }

        if ($countryCode === '') {
            $countryCode = 'PRT';
        }

        $methods = $this->gateway
            ->forCurrency((string) ($account->funding_currency ?? 'EUR'))
            ->listCardDispatchMethods($countryCode, $auditUserId);

        foreach ($methods as $item) {
            if (($item['dispatch_method'] ?? null) !== $method || $item['price'] === null) {
                continue;
            }

            return [
                'dispatch_method' => $method,
                'price_cents' => $this->eurosToCents($item['price']),
                'price' => $item['price'],
            ];
        }

        $feeCents = $this->wallesterSettings->cardValueCents(false);

        return [
            'dispatch_method' => $method,
            'price_cents' => $feeCents,
            'price' => $feeCents / 100,
        ];
    }

    private function eurosToCents(float|int $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{address: string, city: string, state: string, country: string, first_name: string, last_name: string, phone: string, postal_code: string}
     */
    private function validatedCustomerDeliveryAddress(array $data): array
    {
        $address = $data['delivery_address'] ?? null;

        $normalized = [
            'address' => trim((string) (is_array($address) ? ($address['address'] ?? '') : '')),
            'city' => trim((string) (is_array($address) ? ($address['city'] ?? '') : '')),
            'state' => trim((string) (is_array($address) ? ($address['state'] ?? '') : '')),
            'country' => strtoupper(trim((string) (is_array($address) ? ($address['country'] ?? '') : ''))),
            'first_name' => trim((string) (is_array($address) ? ($address['first_name'] ?? '') : '')),
            'last_name' => trim((string) (is_array($address) ? ($address['last_name'] ?? '') : '')),
            'phone' => trim((string) (is_array($address) ? ($address['phone'] ?? '') : '')),
            'postal_code' => trim((string) (is_array($address) ? ($address['postal_code'] ?? '') : '')),
        ];

        if (in_array('', $normalized, true)) {
            throw new ApiException(
                ErrorCode::DELIVERY_ADDRESS_REQUIRED,
                422,
                'Delivery address is required to create a physical card.',
            );
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function storeCustomerDeliveryAddress(Customer $customer, CustomerWallesterCard $card, array $data): void
    {
        $card->customerDeliveryAddress()->create([
            'customer_id' => $customer->id,
            'address' => $data['delivery_address']['address'],
            'city' => $data['delivery_address']['city'],
            'first_name' => $data['delivery_address']['first_name'],
            'last_name' => $data['delivery_address']['last_name'],
            'phone' => $data['delivery_address']['phone'],
            'postal_code' => $data['delivery_address']['postal_code'],
            'state' => $data['delivery_address']['state'],
            'country' => $data['delivery_address']['country'],
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

    private function ensureCardCanRevealSensitiveData(CustomerWallesterCard $card): void
    {
        if ($card->status !== 'Active') {
            throw new ApiException(
                ErrorCode::ACCOUNT_NOT_ACTIVE,
                422,
                'Sensitive card data is only available for active cards.',
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
