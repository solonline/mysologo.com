<?php

namespace App\Domains\Backoffice\Queries;

use App\Domains\Backoffice\DTOs\StripeFeeFormData;
use App\Domains\Backoffice\DTOs\StripeFeeListResult;
use App\Domains\Stripe\Clients\StripeHttpClient;
use App\Models\StripePaymentMethodFee;

class ListStripeFeesQuery
{
    public function __construct(
        protected GetAcceptedCurrenciesQuery $currencies,
        protected StripeHttpClient $stripeClient,
    ) {}

    /**
     * @param  array<string, string|null>  $filters
     */
    public function execute(array $filters): StripeFeeListResult
    {
        $query = StripePaymentMethodFee::query();

        if ($search = $filters['search'] ?? null) {
            $query->where(function ($q) use ($search) {
                $q->where('label', 'like', "%{$search}%")
                    ->orWhere('payment_method', 'like', "%{$search}%");
            });
        }

        if ($currency = $filters['currency'] ?? null) {
            $query->where('currency', strtoupper($currency));
        }

        return new StripeFeeListResult(
            fees: $query->orderBy('label')->paginate(20),
            currencies: $this->currencies->execute(),
        );
    }

    public function formData(): StripeFeeFormData
    {
        try {
            $stripeAvailability = $this->stripeClient->getPaymentMethodAvailability();
        } catch (\Throwable) {
            $stripeAvailability = [];
        }

        return new StripeFeeFormData(
            currencies: $this->currencies->execute(),
            stripeAvailability: $stripeAvailability,
        );
    }

    public function editData(StripePaymentMethodFee $fee): StripeFeeFormData
    {
        return new StripeFeeFormData(
            currencies: $this->currencies->execute(),
            stripeAvailability: [],
        );
    }
}
