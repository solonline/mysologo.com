<?php

namespace App\Contracts;

use App\DTOs\SettingDTO;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;

interface SettingsRepositoryInterface
{
    public function all(): Collection;
    public function get(SettingDTO $dto): ?Setting;
    public function set(SettingDTO $dto): Setting;
}
