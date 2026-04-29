<?php

namespace App\Http\Controllers\Api;

use App\DTOs\SettingDTO;
use App\Http\Controllers\Controller;
use App\Http\Resources\BasicSystemInfoResource;
use App\Services\SettingsService;

class BasicSystemInfo extends Controller
{
    public function __construct(protected SettingsService $settingsService)
    {
    }

    /**
     * @unauthenticated
     *
     */
    public function get()
    {
        $info = $this->settingsService->getInfoSystem();
        $list = [];
        foreach ($info as $key => $value) {
            $list[] = ['key' => $key, 'value' => $value];
        }
        return BasicSystemInfoResource::collection($list);
    }
}
