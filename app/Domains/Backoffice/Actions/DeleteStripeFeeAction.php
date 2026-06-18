<?php

namespace App\Domains\Backoffice\Actions;

use App\Domains\Backoffice\Services\StripeFeeCacheService;
use App\Models\StripePaymentMethodFee;

class DeleteStripeFeeAction
{
    public function __construct(
        protected StripeFeeCacheService $cache,
    ) {}

    public function execute(StripePaymentMethodFee $fee): void
    {
        $currency = $fee->currency;
        $fee->delete();
        $this->cache->invalidateForCurrency($currency);
    }
}
