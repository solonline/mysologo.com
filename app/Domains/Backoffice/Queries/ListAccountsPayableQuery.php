<?php

namespace App\Domains\Backoffice\Queries;

use App\Domains\Backoffice\DTOs\AccountPayableListResult;
use App\Models\AccountPayable;

class ListAccountsPayableQuery
{
    public function __construct(
        protected GetAcceptedCurrenciesQuery $currencies,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function execute(array $filters): AccountPayableListResult
    {
        $query = AccountPayable::query()->withCount('attachments');

        if ($search = $filters['search'] ?? null) {
            $query->where(function ($query) use ($search): void {
                $query->where('supplier', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('document_number', 'like', "%{$search}%");
            });
        }

        if ($supplier = $filters['supplier'] ?? null) {
            $query->where('supplier', 'like', "%{$supplier}%");
        }

        if ($category = $filters['category'] ?? null) {
            $query->where('category', $category);
        }

        if ($currency = $filters['currency'] ?? null) {
            $query->where('currency', strtoupper($currency));
        }

        if (! empty($filters['overdue'])) {
            $query->overdue();
        } elseif ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($dueFrom = $filters['due_from'] ?? null) {
            $query->whereDate('due_date', '>=', $dueFrom);
        }

        if ($dueTo = $filters['due_to'] ?? null) {
            $query->whereDate('due_date', '<=', $dueTo);
        }

        return new AccountPayableListResult(
            accounts: $query
                ->orderByRaw("case when status = 'pending' then 0 when status = 'paid' then 1 else 2 end")
                ->orderBy('due_date')
                ->paginate(20),
            categories: AccountPayable::query()
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category'),
            currencies: $this->currencies->execute(),
            statuses: AccountPayable::statuses(),
        );
    }

    public function findForEdit(AccountPayable $account): AccountPayable
    {
        return $account->load('attachments');
    }
}
