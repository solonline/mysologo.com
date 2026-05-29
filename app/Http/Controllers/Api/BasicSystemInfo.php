<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BasicSystemInfoResource;
use App\Services\SettingsService;

class BasicSystemInfo extends Controller
{
    public function __construct(protected SettingsService $settingsService)
    {
    }

    /**
     * System Info
     *
     * Returns public system settings such as app version, supported currencies, and feature flags. No authentication required.
     *
     * @tags System
     * @unauthenticated
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
