<?php
namespace App\Services;

use App\Contracts\SettingsRepositoryInterface;
use App\DTOs\SettingDTO;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;

class SettingsService
{
    public function __construct(
        private readonly SettingsRepositoryInterface $settingRepository
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
        $infoBasics = ['company_name', 'email', 'phone', 'address', 'city', 'state', 'postal_code', 'country', 'fiscal_id', 'website', 'support_email', 'description', 'social_links', 'maintenance_mode', 'logo', 'favicon', 'timezone'];
        foreach ($infoBasics as $key) {
            $value = $this->getByKey($key);
            $infoSytem[$key] = $value;
        }
        return $infoSytem;
    }

    /** internal queries */
    public function getByKey(string $key): ?string
    {
        return $this->get(new SettingDTO($key, null))?->value;
    }

    public function set(SettingDTO $dto): void
    {
        $this->settingRepository->set($dto);
    }
}
