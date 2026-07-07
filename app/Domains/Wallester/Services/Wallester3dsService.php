<?php

namespace App\Domains\Wallester\Services;

use App\Domains\Customer\Services\CustomerNotificationService;
use App\Domains\Wallester\Enums\Wallester3dsChallengeStatus;
use App\Domains\Wallester\Enums\Wallester3dsValidationStatus;
use App\Models\Customer;
use App\Models\CustomerWallesterAccount;
use App\Models\CustomerWallesterCard;
use App\Models\Wallester3dsChallenge;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class Wallester3dsService
{
    public function __construct(
        protected CustomerNotificationService $notifications,
        protected WallesterSettingsService $wallesterSettings,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleInitiateAction(string $xRequestId, array $payload): Wallester3dsChallenge
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $requestId = (string) ($data['request_id'] ?? '');

        if ($requestId === '') {
            throw new RuntimeException('Wallester 3DS initiate action is missing request_id.');
        }

        $existing = Wallester3dsChallenge::query()
            ->where('wallester_request_id', $requestId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $cardData = is_array($data['card'] ?? null) ? $data['card'] : [];
        $wallesterCardId = (string) ($cardData['id'] ?? '');

        if ($wallesterCardId === '') {
            throw new RuntimeException('Wallester 3DS initiate action is missing card id.');
        }

        $localCard = CustomerWallesterCard::query()
            ->with('wallesterAccount.customer')
            ->where('wallester_card_id', $wallesterCardId)
            ->first();

        $customer = $localCard?->wallesterAccount?->customer;
        $ttlSeconds = $this->wallesterSettings->threeDsChallengeTtlSeconds();

        $challenge = Wallester3dsChallenge::query()->create([
            'wallester_request_id' => $requestId,
            'wallester_x_request_id' => $xRequestId,
            'customer_id' => $customer?->id,
            'customer_wallester_card_id' => $localCard?->id,
            'wallester_card_id' => $wallesterCardId,
            'status' => Wallester3dsChallengeStatus::Pending,
            'card' => $cardData,
            'merchant' => is_array($data['merchant'] ?? null) ? $data['merchant'] : null,
            'transaction' => is_array($data['transaction'] ?? null) ? $data['transaction'] : null,
            'expires_at' => now()->addSeconds(max($ttlSeconds, 30)),
        ]);

        if ($customer !== null) {
            $this->notifyCustomer($customer, $challenge);
        }

        return $challenge;
    }

    public function resolveValidation(string $xRequestId, string $wallesterRequestId): Wallester3dsValidationStatus
    {
        $challenge = Wallester3dsChallenge::query()
            ->where('wallester_request_id', $wallesterRequestId)
            ->where('wallester_x_request_id', $xRequestId)
            ->first();

        if ($challenge === null) {
            return Wallester3dsValidationStatus::Error;
        }

        if ($challenge->status === Wallester3dsChallengeStatus::Pending && $challenge->isExpired()) {
            $challenge->update([
                'status' => Wallester3dsChallengeStatus::Expired,
                'resolved_at' => now(),
            ]);

            return Wallester3dsValidationStatus::Error;
        }

        return match ($challenge->status) {
            Wallester3dsChallengeStatus::Approved => Wallester3dsValidationStatus::Success,
            Wallester3dsChallengeStatus::Rejected => Wallester3dsValidationStatus::Rejected,
            Wallester3dsChallengeStatus::Pending => Wallester3dsValidationStatus::Pending,
            Wallester3dsChallengeStatus::Expired => Wallester3dsValidationStatus::Error,
        };
    }

    /** @return Collection<int, Wallester3dsChallenge> */
    public function listPendingForCustomer(Customer $customer): Collection
    {
        return Wallester3dsChallenge::query()
            ->where('customer_id', $customer->id)
            ->where('status', Wallester3dsChallengeStatus::Pending)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->get();
    }

    public function approve(Wallester3dsChallenge $challenge, Customer $customer): Wallester3dsChallenge
    {
        return $this->resolveChallenge($challenge, $customer, Wallester3dsChallengeStatus::Approved);
    }

    public function reject(Wallester3dsChallenge $challenge, Customer $customer): Wallester3dsChallenge
    {
        return $this->resolveChallenge($challenge, $customer, Wallester3dsChallengeStatus::Rejected);
    }

    /**
     * @return array{out_of_band_enabled: true, out_of_band_id: string}
     */
    public function outOfBandSettingsFor(CustomerWallesterAccount $account): array
    {
        return [
            'out_of_band_enabled' => true,
            'out_of_band_id' => (string) $account->wallester_oob_id,
        ];
    }

    private function resolveChallenge(
        Wallester3dsChallenge $challenge,
        Customer $customer,
        Wallester3dsChallengeStatus $status,
    ): Wallester3dsChallenge {
        if ($challenge->customer_id !== $customer->id) {
            throw new RuntimeException('This 3DS challenge does not belong to the authenticated customer.');
        }

        if (! $challenge->isResolvable()) {
            throw new RuntimeException('This 3DS challenge can no longer be resolved.');
        }

        return DB::transaction(function () use ($challenge, $status): Wallester3dsChallenge {
            $challenge->update([
                'status' => $status,
                'resolved_at' => now(),
            ]);

            return $challenge->fresh();
        });
    }

    private function notifyCustomer(Customer $customer, Wallester3dsChallenge $challenge): void
    {
        $merchantName = (string) ($challenge->merchant['name'] ?? 'Online merchant');
        $amount = (string) ($challenge->transaction['amount'] ?? '');
        $currency = (string) ($challenge->transaction['currency'] ?? '');
        $amountLabel = trim($amount.' '.$currency);

        $this->notifications->notify(
            $customer,
            'wallester.3ds_challenge',
            'Confirm online payment',
            $amountLabel !== '' ? "{$merchantName} — {$amountLabel}" : $merchantName,
            [
                'challenge_id' => $challenge->id,
                'wallester_request_id' => $challenge->wallester_request_id,
                'merchant' => $challenge->merchant,
                'transaction' => $challenge->transaction,
                'card' => $challenge->card,
                'expires_at' => $challenge->expires_at?->toIso8601String(),
            ],
        );
    }
}
