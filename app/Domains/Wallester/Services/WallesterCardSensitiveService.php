<?php

namespace App\Domains\Wallester\Services;

use App\Domains\Auth\Services\DeviceAuthService;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\CustomerWallesterCard;
use Illuminate\Support\Facades\Hash;

class WallesterCardSensitiveService
{
    public function __construct(
        private readonly WallesterGatewayInterface $gateway,
        private readonly WallesterCardRsaKeyService $rsaKeys,
        private readonly WallesterSensitiveDataDecryptor $decryptor,
        private readonly DeviceAuthService $deviceAuthService,
    ) {}

    /**
     * @param  array{password?: string, pin?: string, device_token?: string}  $stepUpCredentials
     * @return array{card_number: string, cvv: string, expiry_date: ?string}
     */
    public function revealForCustomer(Customer $customer, CustomerWallesterCard $card, array $stepUpCredentials): array
    {
        $this->verifyStepUpCredentials($customer, $stepUpCredentials);
        $this->ensureCustomerOwnsCard($customer, $card);
        $this->ensureCardCanRevealSensitiveData($card);

        $auditUserId = (string) $customer->id;
        $publicKey = $this->rsaKeys->publicKeyBase64();
        $wallesterCardId = $card->wallester_card_id;

        $cardDetails = $this->gateway->getCard($wallesterCardId, $auditUserId);
        $encryptedCardNumber = $this->gateway->getEncryptedCardNumber($wallesterCardId, $publicKey, $auditUserId);
        $encryptedCvv = $this->gateway->getEncryptedCvv2($wallesterCardId, $publicKey, $auditUserId);

        try {
            return [
                'card_number' => $this->decryptor->decryptCardNumber($encryptedCardNumber),
                'cvv' => $this->decryptor->decryptCvv2($encryptedCvv),
                'expiry_date' => isset($cardDetails['expiry_date']) ? (string) $cardDetails['expiry_date'] : $card->expiry_date?->toIso8601String(),
            ];
        } catch (\RuntimeException $exception) {
            throw new ApiException(
                ErrorCode::INTERNAL_ERROR,
                500,
                'Unable to decrypt Wallester card sensitive data.',
                previous: $exception,
            );
        }
    }

    /**
     * @param  array{password?: string, pin?: string, device_token?: string}  $stepUpCredentials
     */
    private function verifyStepUpCredentials(Customer $customer, array $stepUpCredentials): void
    {
        if (isset($stepUpCredentials['password'])) {
            if (! Hash::check($stepUpCredentials['password'], $customer->password)) {
                throw new ApiException(
                    ErrorCode::INVALID_CREDENTIALS,
                    401,
                    'Invalid password.',
                );
            }

            return;
        }

        $this->deviceAuthService->verifyPin(
            $customer,
            (string) $stepUpCredentials['device_token'],
            (string) $stepUpCredentials['pin'],
        );
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
}
