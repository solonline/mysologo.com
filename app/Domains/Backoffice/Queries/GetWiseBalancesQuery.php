<?php

namespace App\Domains\Backoffice\Queries;

use App\Domains\Wise\Contracts\WiseGatewayInterface;
use App\Models\CustomerBalance;
use Illuminate\Support\Facades\Log;
use Throwable;

class GetWiseBalancesQuery
{
    public function __construct(
        private readonly WiseGatewayInterface $gateway,
    ) {}

    /**
     * @return array{
     *     balances: list<array{currency: string, amount: float|null, customers_cents: int, sufficient: bool|null}>,
     *     error: string|null
     * }
     */
    public function execute(): array
    {
        $allocations = $this->customerAllocationsByCurrency();

        try {
            $balances = $this->gateway->listBalances();
        } catch (Throwable $exception) {
            Log::channel('wise')->warning('wise_dashboard_balances_failed', [
                'message' => $exception->getMessage(),
            ]);

            return [
                'balances' => $this->mergeBalances([], $allocations),
                'error' => 'Não foi possível consultar a Wise.',
            ];
        }

        return [
            'balances' => $this->mergeBalances($balances, $allocations),
            'error' => null,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function customerAllocationsByCurrency(): array
    {
        $allocations = [];

        $rows = CustomerBalance::query()
            ->selectRaw('currency, SUM(wise_allocated) as allocated')
            ->groupBy('currency')
            ->get();

        foreach ($rows as $row) {
            $currency = strtoupper((string) $row->currency);

            if ($currency === '') {
                continue;
            }

            $allocations[$currency] = (int) $row->allocated;
        }

        return $allocations;
    }

    /**
     * @param  list<array{id?: int|string|null, currency?: string|null, amount?: float|null, reserved_amount?: float|null, type?: string|null}>  $balances
     * @param  array<string, int>  $allocations
     * @return list<array{currency: string, amount: float|null, customers_cents: int, sufficient: bool|null}>
     */
    private function mergeBalances(array $balances, array $allocations): array
    {
        $wiseByCurrency = [];

        foreach ($balances as $balance) {
            $currency = strtoupper((string) ($balance['currency'] ?? ''));

            if ($currency === '') {
                continue;
            }

            $wiseByCurrency[$currency] = $balance['amount'] ?? null;
        }

        $currencies = collect(array_keys($wiseByCurrency))
            ->merge(array_keys($allocations))
            ->unique()
            ->sortBy(fn (string $currency): string => $this->currencyRank($currency))
            ->values();

        $rows = [];

        foreach ($currencies as $currency) {
            $amount = $wiseByCurrency[$currency] ?? null;
            $customersCents = $allocations[$currency] ?? 0;
            $wiseCents = is_numeric($amount) ? (int) round(((float) $amount) * 100) : null;

            $rows[] = [
                'currency' => $currency,
                'amount' => is_numeric($amount) ? (float) $amount : null,
                'customers_cents' => $customersCents,
                'sufficient' => $wiseCents === null ? null : $wiseCents >= $customersCents,
            ];
        }

        return $rows;
    }

    private function currencyRank(?string $currency): string
    {
        $currency = strtoupper((string) $currency);
        $priority = ['EUR' => '0', 'USD' => '1', 'GBP' => '2', 'CHF' => '3'];

        return ($priority[$currency] ?? '9').$currency;
    }
}
