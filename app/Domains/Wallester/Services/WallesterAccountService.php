<?php

namespace App\Domains\Wallester\Services;

use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Models\Customer;

class WallesterAccountService
{
    private const PAGE_SIZE = 1000;

    public function __construct(
        protected WallesterGatewayInterface $gateway,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{total_records_number: int, accounts: array<int, array<string, mixed>>}
     */
    public function listForCustomer(Customer $customer, array $filters, string $currency = 'EUR'): array
    {
        $baseQuery = $this->buildWallesterQuery($filters);
        $auditUserId = (string) $customer->id;
        $accounts = [];
        $fromRecord = 0;
        $totalRecordsNumber = 0;

        do {
            $response = $this->gateway->forCurrency($currency)->listAccounts([
                ...$baseQuery,
                'from_record' => $fromRecord,
                'records_count' => self::PAGE_SIZE,
            ], $auditUserId);

            $totalRecordsNumber = $response['total_records_number'];
            $page = $response['accounts'];

            if ($page === []) {
                break;
            }

            array_push($accounts, ...$page);
            $fromRecord += count($page);
        } while ($fromRecord < $totalRecordsNumber);

        return [
            'total_records_number' => $totalRecordsNumber,
            'accounts' => $accounts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function showForCustomer(Customer $customer, string $wallesterAccountId, string $currency = 'EUR'): array
    {
        return $this->gateway->forCurrency($currency)->getAccount($wallesterAccountId, (string) $customer->id);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildWallesterQuery(array $filters): array
    {
        $query = [];

        foreach ([
            'name',
            'external_id',
            'is_main',
            'exceeding_credit_limit',
            'order_direction',
            'order_field',
            'company_id',
            'person_id',
        ] as $field) {
            if (array_key_exists($field, $filters)) {
                $query[$field] = $filters[$field];
            }
        }

        return $query;
    }
}
