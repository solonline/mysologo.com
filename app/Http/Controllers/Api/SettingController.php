<?php

namespace App\Http\Controllers\Api;

use App\DTOs\SettingDTO;
use App\Http\Controllers\Controller;
use App\Http\Resources\SettingResource;
use App\Services\SettingsService;

class SettingController extends Controller
{
    public function __construct(private readonly SettingsService $settingsService)
    {
    }

    /**
     * List Settings
     *
     * Returns all public application settings as key-value pairs.
     *
     * @tags Settings
     */
    public function index()
    {
        return SettingResource::collection($this->settingsService->all());
    }

    /**
     * Get Setting
     *
     * Returns a single setting by its key.
     *
     * @tags Settings
     */
    public function get(string $key)
    {
        $dto = SettingDTO::fromArray(['key' => $key, 'value' => null]);
        $setting = $this->settingsService->get($dto);

        if ($setting === null) {
            return response()->json(['message' => 'Setting not found'], 404);
        }

        return SettingResource::make($setting);
    }
}
