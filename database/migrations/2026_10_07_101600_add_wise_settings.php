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
                'key' => 'wise.base_url',
                'label' => 'Base URL',
                'type' => 'url',
                'value' => env('WISE_BASE_URL', 'https://api.wise-sandbox.com'),
            ],
            [
                'key' => 'wise.api_token',
                'label' => 'API Token',
                'type' => 'secret',
                'value' => env('WISE_API_TOKEN', ''),
            ],
            [
                'key' => 'wise.profile_id',
                'label' => 'Profile ID',
                'type' => 'text',
                'value' => env('WISE_PROFILE_ID', ''),
            ],
            [
                'key' => 'wise.webhook_public_key',
                'label' => 'Webhook Public Key (PEM)',
                'type' => 'textarea',
                'value' => env('WISE_WEBHOOK_PUBLIC_KEY', ''),
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
            'wise.base_url',
            'wise.api_token',
            'wise.profile_id',
            'wise.webhook_public_key',
        ])->delete();
    }
};
