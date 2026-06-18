<?php

namespace App\Domains\Backoffice\Actions;

use App\Domains\Backoffice\DTOs\StripeFeeData;
use App\Domains\Backoffice\Services\StripeFeeCacheService;
use App\Models\StripePaymentMethodFee;

class CreateStripeFeeAction
{
    public function __construct(
        protected StripeFeeCacheService $cache,
    ) {}

    public function execute(StripeFeeData $data): StripePaymentMethodFee
    {
        $fee = StripePaymentMethodFee::create($data->toAttributes());
        $this->cache->invalidateForCurrency($data->currency);

        return $fee;
    }
}
