<?php

namespace App\Domains\Settings\Controllers;

use App\Domains\Settings\Services\SettingsService;
use App\Domains\Settings\DTOs\SettingDTO;
use App\Http\Resources\SettingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SettingController extends Controller
{
    public function __construct(private readonly SettingsService $settingsService) {}

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

    /**
     * Update Setting
     *
     * Updates a single setting value by key.
     *
     * @tags Settings
     */
    public function set(Request $request, string $key): JsonResponse
    {
        $this->settingsService->set(new SettingDTO(
            key: $key,
            value: (string) $request->input('value', ''),
        ));

        return response()->json(['ok' => true]);
    }
}
