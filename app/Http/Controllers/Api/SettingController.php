<?php

namespace App\Http\Controllers\Api;

use App\DTOs\SettingDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\SetSettingRequest;
use App\Http\Resources\SettingResource;
use App\Services\SettingsService;

class SettingController extends Controller
{
    public function __construct(private readonly SettingsService $settingsService)
    {
    }

    /**
     * Summary of index
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        return SettingResource::collection($this->settingsService->all());
    }

    /**
     * Summary of get
     * @param string $key
     * @return \Illuminate\Http\JsonResponse|SettingResource
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
     * Summary of set
     * @param SetSettingRequest $request
     * @param string $key
     * @return \Illuminate\Http\JsonResponse
     */
    public function set(SetSettingRequest $request, string $key)
    {
        $dto = SettingDTO::fromArray(['key' => $key, 'value' => $request->validated('value')]);
        $this->settingsService->set($dto);

        return response()->json(['message' => 'Setting updated successfully']);
    }
}
