<?php

namespace App\Domains\Backoffice\Services;

use App\Domains\Settings\DTOs\SettingDTO;
use App\Domains\Settings\Services\SettingsService;
use Illuminate\Support\Collection;

class SettingsManagementService
{
    public function __construct(
        protected SettingsService $settings,
    ) {}

    public function allKeyedByKey(): Collection
    {
        return $this->settings->all()->keyBy('key');
    }

    /**
     * @param  array<string, string|null>  $settings
     */
    public function updateMany(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->settings->set(new SettingDTO(
                key: $key,
                value: $value ?? '',
            ));
        }
    }
}
