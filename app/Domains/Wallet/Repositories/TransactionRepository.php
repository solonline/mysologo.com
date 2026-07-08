<?php

namespace App\Domains\Wallet\Repositories;

use App\Domains\Wallet\Contracts\TransactionRepositoryInterface;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\StripeTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TransactionRepository implements TransactionRepositoryInterface
{
    public function getByCustomer(Customer $customer): Collection
    {
        return StripeTransaction::where('customer_id', $customer->id)
            ->latest()
            ->get();
    }

    public function getRecent(Customer $customer, int $limit = 10): Collection
    {
        return StripeTransaction::where('customer_id', $customer->id)
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function getPaginated(Customer $customer, int $perPage = 15): LengthAwarePaginator
    {
        return StripeTransaction::where('customer_id', $customer->id)
            ->latest()
            ->paginate($perPage);
    }

    public function findForCustomer(Customer $customer, int $id): ?StripeTransaction
    {
        return StripeTransaction::where('customer_id', $customer->id)
            ->find($id);
    }

    public function create(array $data): StripeTransaction
    {
        return StripeTransaction::create($data);
    }

    public function getAvailableBalance(int $customerId, string $currency): int
    {
        $balance = CustomerBalance::where('customer_id', $customerId)
            ->where('currency', strtoupper($currency))
            ->first();

        return $balance ? (int) $balance->available : 0;
    }

    public function adjustBalance(int $customerId, string $currency, int $delta): void
    {
        $balance = CustomerBalance::firstOrCreate(
            ['customer_id' => $customerId, 'currency' => strtoupper($currency)],
            ['available' => 0, 'pending' => 0],
        );

        CustomerBalance::where('id', $balance->id)->update([
            'available' => DB::raw("available + {$delta}"),
        ]);
    }
}
