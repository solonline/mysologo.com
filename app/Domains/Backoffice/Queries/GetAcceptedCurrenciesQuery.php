<?php

namespace App\Domains\Backoffice\Queries;

use App\Domains\Settings\Services\SettingsService;

class GetAcceptedCurrenciesQuery
{
    public function __construct(
        protected SettingsService $settings,
    ) {}

    /** @return list<string> */
    public function execute(): array
    {
        $currencies = json_decode($this->settings->getByKey('currency.accepted') ?? '', true);

        if (! is_array($currencies) || $currencies === []) {
            return ['EUR'];
        }

        return array_map('strtoupper', $currencies);
    }
}
