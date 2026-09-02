<?php

namespace App\Domains\Backoffice\Queries;

use App\Domains\Wallet\Enums\TransactionType;
use App\Domains\Wallet\Services\TopupWallesterFundingPolicy;
use App\Models\StripeTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListStripeTransactionsQuery
{
    public function __construct(
        protected TopupWallesterFundingPolicy $fundingPolicy,
    ) {}
    /**
     * @param  array<string, string|null>  $filters
     */
    public function execute(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = StripeTransaction::query()
            ->with(['customer', 'relatedStripeTransaction.customer'])
            ->where('type', '!=', TransactionType::P2P)
            ->latest('stripe_created_at')
            ->latest('id');

        if ($customerId = $filters['customer_id'] ?? null) {
            $query->where('customer_id', $customerId);
        }

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($q) use ($search) {
                if (is_numeric($search)) {
                    $q->orWhere('id', (int) $search);
                }

                $q->orWhere('stripe_payment_intent_id', 'like', "%{$search}%")
                    ->orWhere('stripe_charge_id', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($type = $filters['type'] ?? null) {
            $query->where('type', $type);
        }

        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($partnerFundingStatus = $filters['partner_funding_status'] ?? null) {
            $query->where('partner_funding_status', $partnerFundingStatus);
        }

        if ($from = $filters['from'] ?? null) {
            $query->whereDate('stripe_created_at', '>=', $from);
        }

        if ($to = $filters['to'] ?? null) {
            $query->whereDate('stripe_created_at', '<=', $to);
        }

        if (! empty($filters['with_platform_fee'])) {
            $query->where('platform_fee', '>', 0);
        }

        if (! empty($filters['after_accounting_cutoff'])) {
            $this->fundingPolicy->constrainAfterAccountingCutoff($query);
        }

        return $query->paginate($perPage);
    }
}
