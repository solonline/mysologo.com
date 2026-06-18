<?php

namespace App\Domains\Settings\Services;

use App\Domains\Settings\Contracts\SettingsRepositoryInterface;
use App\Domains\Settings\DTOs\SettingDTO;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;

class SettingsService
{
    public function __construct(
        private readonly SettingsRepositoryInterface $settingRepository,
    ) {}

    public function get(SettingDTO $dto): ?Setting
    {
        return $this->settingRepository->get($dto);
    }

    public function all(): Collection
    {
        return $this->settingRepository->all();
    }

    public function getInfoSystem(): array
    {
        $infoSytem = [];
        $infoBasics = ['company.name', 'company.email', 'company.phone', 'address.street', 'address.city', 'address.state', 'address.postal_code', 'address.country', 'company.fiscal_id', 'company.website', 'company.support_email', 'company.description', 'company.social_links', 'system.maintenance_mode', 'company.logo', 'company.favicon', 'system.timezone', 'currency.accepted'];
        foreach ($infoBasics as $key) {
            $value = $this->getByKey($key);
            $infoSytem[$key] = $value;
        }

        return $infoSytem;
    }

    public function getByKey(string $key): ?string
    {
        return $this->get(new SettingDTO($key, null))?->value;
    }

    public function set(SettingDTO $dto): void
    {
        $this->settingRepository->set($dto);
    }
}
