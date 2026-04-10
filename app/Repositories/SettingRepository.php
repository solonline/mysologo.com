<?php

namespace App\Repositories;

use App\Contracts\SettingsRepositoryInterface;
use App\DTOs\SettingDTO;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;

class SettingRepository implements SettingsRepositoryInterface
{
    public function get(SettingDTO $dto): ?Setting
    {
        return Setting::where('key', $dto->key)->first();
    }

    public function all(): Collection
    {
        return Setting::all();
    }

    public function set(SettingDTO $dto): Setting
    {
        return Setting::updateOrCreate(
            ['key' => $dto->key],
            ['value' => $dto->value]
        );
    }
}
