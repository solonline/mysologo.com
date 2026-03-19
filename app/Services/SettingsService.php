<?php
namespace App\Services;

use App\Models\Setting;

class SettingsService
{
    public function get($key)
    {
        $setting = Setting::where('key', $key)->first();
        return $setting ? $setting->value : null;
    }

    public function set($key, $value)
    {
        $setting = Setting::where('key', $key)->first();
        if ($setting) {
            $setting->value = $value;
            $setting->save();
        } else {
            Setting::create(['key' => $key, 'value' => $value]);
        }
    }
}
