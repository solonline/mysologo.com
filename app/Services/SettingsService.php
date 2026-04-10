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
