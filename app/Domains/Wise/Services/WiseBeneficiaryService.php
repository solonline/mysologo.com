<?php

namespace App\Domains\Wise\Services;

use App\Domains\Wise\Contracts\WiseGatewayInterface;
use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Models\CustomerWiseBeneficiary;

class WiseBeneficiaryService
{
    public function __construct(
        private readonly WiseGatewayInterface $gateway,
        private readonly WiseSettingsService $settings,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listFor(Customer $customer): array
    {
        return $customer->wiseBeneficiaries()
            ->orderBy('id')
            ->get()
            ->map(fn (CustomerWiseBeneficiary $beneficiary): array => $beneficiary->toApiArray())
            ->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{beneficiary: CustomerWiseBeneficiary, created: bool}
     */
    public function create(Customer $customer, array $input): array
    {
        if (! $this->settings->hasCredentials()) {
            throw new ApiException(
                'wise_not_configured',
                503,
                'Wise credentials are not configured.',
                'wise',
            );
        }

        $owned = $this->findOwned($customer, $input);

        if ($owned instanceof CustomerWiseBeneficiary) {
            return ['beneficiary' => $owned, 'created' => false];
        }

        $shared = $this->findShared($input);

        if ($shared instanceof CustomerWiseBeneficiary) {
            return [
                'beneficiary' => $this->copyForCustomer($customer, $shared, $input),
                'created' => true,
            ];
        }

        if (! $this->hasAccountPayload($input)) {
            throw new ApiException(
                'wise_beneficiary_not_found',
                422,
                'This beneficiary is not linked to a customer.',
                'wise',
            );
        }

        $response = $this->gateway->createRecipient($this->wisePayload($input));
        $recipientId = $response['id'] ?? null;

        if (! is_numeric($recipientId)) {
            throw new ApiException(
                'wise_create_recipient_failed',
                502,
                'Wise did not return a recipient id.',
                'wise',
            );
        }

        $beneficiary = CustomerWiseBeneficiary::query()->create([
            'customer_id' => $customer->id,
            'wise_recipient_id' => (int) $recipientId,
            'wise_profile_id' => (string) ($response['profileId'] ?? $this->settings->profileId()),
            'currency' => strtoupper((string) $input['currency']),
            'type' => (string) $input['type'],
            'account_holder_name' => (string) $input['account_holder_name'],
            'owned_by_customer' => (bool) $input['owned_by_customer'],
            'details' => $this->canonicalDetails($input['details']),
            'ultimate_beneficiary' => $input['ultimate_beneficiary'] ?? null,
            'wise_hash' => isset($response['hash']) ? (string) $response['hash'] : null,
            'account_summary' => isset($response['accountSummary']) ? (string) $response['accountSummary'] : null,
            'active' => (bool) ($response['active'] ?? true),
        ]);

        return ['beneficiary' => $beneficiary, 'created' => true];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function findOwned(Customer $customer, array $input): ?CustomerWiseBeneficiary
    {
        if (isset($input['wise_recipient_id'])) {
            $byId = $customer->wiseBeneficiaries()
                ->where('wise_recipient_id', (int) $input['wise_recipient_id'])
                ->first();

            if ($byId instanceof CustomerWiseBeneficiary) {
                return $byId;
            }
        }

        if (! $this->hasAccountPayload($input)) {
            return null;
        }

        return $this->findByAccount($customer->id, $input);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function findShared(array $input): ?CustomerWiseBeneficiary
    {
        if (isset($input['wise_recipient_id'])) {
            $byId = CustomerWiseBeneficiary::query()
                ->where('wise_recipient_id', (int) $input['wise_recipient_id'])
                ->first();

            if ($byId instanceof CustomerWiseBeneficiary) {
                return $byId;
            }
        }

        if (! $this->hasAccountPayload($input)) {
            return null;
        }

        return $this->findByAccount(null, $input);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function findByAccount(?int $customerId, array $input): ?CustomerWiseBeneficiary
    {
        $query = CustomerWiseBeneficiary::query()
            ->where('currency', strtoupper((string) $input['currency']))
            ->where('type', (string) $input['type']);

        if ($customerId !== null) {
            $query->where('customer_id', $customerId);
        }

        return $query->get()->first(
            fn (CustomerWiseBeneficiary $beneficiary): bool => $this->sameDetails(
                $beneficiary->details ?? [],
                $input['details'],
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function copyForCustomer(Customer $customer, CustomerWiseBeneficiary $shared, array $input): CustomerWiseBeneficiary
    {
        return CustomerWiseBeneficiary::query()->create([
            'customer_id' => $customer->id,
            'wise_recipient_id' => $shared->wise_recipient_id,
            'wise_profile_id' => $shared->wise_profile_id,
            'currency' => $shared->currency,
            'type' => $shared->type,
            'account_holder_name' => (string) ($input['account_holder_name'] ?? $shared->account_holder_name),
            'owned_by_customer' => array_key_exists('owned_by_customer', $input)
                ? (bool) $input['owned_by_customer']
                : $shared->owned_by_customer,
            'details' => $shared->details,
            'ultimate_beneficiary' => $input['ultimate_beneficiary'] ?? $shared->ultimate_beneficiary,
            'wise_hash' => $shared->wise_hash,
            'account_summary' => $shared->account_summary,
            'active' => $shared->active,
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function hasAccountPayload(array $input): bool
    {
        return isset($input['currency'], $input['type'], $input['details']) && is_array($input['details']);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function wisePayload(array $input): array
    {
        $profileId = $this->settings->profileId();

        $payload = [
            'currency' => strtoupper((string) $input['currency']),
            'type' => (string) $input['type'],
            'profile' => ctype_digit($profileId) ? (int) $profileId : $profileId,
            'accountHolderName' => (string) $input['account_holder_name'],
            'ownedByCustomer' => (bool) $input['owned_by_customer'],
            'details' => $input['details'],
        ];

        if (isset($input['ultimate_beneficiary']) && is_array($input['ultimate_beneficiary'])) {
            $payload['ultimateBeneficiary'] = $input['ultimate_beneficiary'];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function canonicalDetails(array $details): array
    {
        return $this->sortRecursive($details);
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function sameDetails(array $left, array $right): bool
    {
        return json_encode($this->canonicalDetails($left)) === json_encode($this->canonicalDetails($right));
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function sortRecursive(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortRecursive($item);
            }
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
