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
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WallesterService
{
    public function __construct(
        protected WallesterGatewayInterface $gateway,
        protected WallesterAccountRepositoryInterface $wallesterAccountRepository,
        protected Wallester3dsService $threeDsService,
    ) {}

    public function createAccount(array $data): array
    {
        return $this->gateway->createAccount($data);
    }

    public function showCardForCustomer(Customer $customer, CustomerWallesterCard $card): array
    {
        $this->ensureCustomerOwnsCard($customer, $card);

        return $this->gateway->getCard($card->wallester_card_id, (string) $customer->id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createCardForCustomer(Customer $customer, array $data): CustomerWallesterCard
    {
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

        $card = $this->createCard(
            $this->buildCreateCardPayload($customer, $account, $data),
            (string) $customer->id,
        );

        if ($card->type === 'Virtual' && $card->status === 'Created') {
            return $this->activateVirtualCardForCustomer($customer, $card);
        }

        return $card;
    }

    public function activateVirtualCardForCustomer(Customer $customer, CustomerWallesterCard $card): CustomerWallesterCard
    {
        $this->ensureCustomerOwnsCard($customer, $card);
        $this->ensureVirtualCardActivatable($card);

        if ($card->status === 'Active') {
            return $card;
        }

        $wallesterCard = $this->gateway->activateCard($card->wallester_card_id, (string) $customer->id);

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

        $card = $this->gateway->createCard($data, $auditUserId);

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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildCreateCardPayload(Customer $customer, CustomerWallesterAccount $account, array $data): array
    {
        unset($data['account_id'], $data['3d_secure_settings']);

        $security = is_array($data['security'] ?? null) ? $data['security'] : [];

        return array_merge($data, [
            'account_id' => $account->wallester_account_id,
            '3d_secure_settings' => $this->threeDsService->outOfBandSettingsFor($customer),
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
