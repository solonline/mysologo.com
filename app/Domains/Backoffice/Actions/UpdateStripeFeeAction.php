<?php

namespace App\Domains\Backoffice\Actions;

use App\Domains\Backoffice\DTOs\StripeFeeData;
use App\Domains\Backoffice\Services\StripeFeeCacheService;
use App\Models\StripePaymentMethodFee;

class UpdateStripeFeeAction
{
    public function __construct(
        protected StripeFeeCacheService $cache,
    ) {}

    public function execute(StripePaymentMethodFee $fee, StripeFeeData $data): StripePaymentMethodFee
    {
        $previousCurrency = $fee->currency;

        $fee->update($data->toAttributes());

        $this->cache->invalidateForCurrency($data->currency);

        if ($previousCurrency !== $data->currency) {
            $this->cache->invalidateForCurrency($previousCurrency);
        }

        return $fee->fresh();
    }
}
