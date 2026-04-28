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
        $infoBasics = ['company_name', 'email', 'phone', 'address', 'city', 'state', 'postal_code', 'country', 'fiscal_id', 'website', 'support_email', 'description', 'social_links', 'maintenance_mode', 'logo', 'favicon', 'timezone'];
        $settings = [];
        foreach ($infoBasics as $key) {
            $value = $this->settingsService->getByKey($key);
            $settings[] = new SettingDTO($key, $value);
        }
        return BasicSystemInfoResource::collection($settings);
    }
}
