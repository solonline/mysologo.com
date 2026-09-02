<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $settings = [
            [
                'key' => 'fx.base_url',
                'label' => 'URL Base',
                'type' => 'url',
                'value' => env('FX_BASE_URL', 'https://economia.awesomeapi.com.br'),
            ],
            [
                'key' => 'fx.api_key',
                'label' => 'API Key (x-api-key)',
                'type' => 'secret',
                'value' => env('FX_API_KEY', ''),
            ],
            [
                'key' => 'fx.cache_ttl_seconds',
                'label' => 'Cache TTL (segundos)',
                'type' => 'number',
                'value' => (string) env('FX_CACHE_TTL', '60'),
            ],
        ];

        foreach ($settings as $setting) {
            if (DB::table('settings')->where('key', $setting['key'])->exists()) {
                continue;
            }

            DB::table('settings')->insert(array_merge($setting, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'fx.base_url',
            'fx.api_key',
            'fx.cache_ttl_seconds',
        ])->delete();
    }
};
