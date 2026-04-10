<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'company_name', 'value' => 'Empresa Exemplo'],
            ['key' => 'email', 'value' => 'contato@empresa.com'],
            ['key' => 'phone', 'value' => '+351999999999'],
            ['key' => 'address', 'value' => 'Rua Exemplo, 123'],
            ['key' => 'city', 'value' => 'Lisboa'],
            ['key' => 'state', 'value' => 'Lisboa'],
            ['key' => 'postal_code', 'value' => '1000-000'],
            ['key' => 'country', 'value' => 'Portugal'],
            ['key' => 'fiscal_id', 'value' => '123456789'],
            ['key' => 'website', 'value' => 'https://empresa.com'],
            ['key' => 'support_email', 'value' => 'suporte@empresa.com'],
            ['key' => 'description', 'value' => 'Empresa de exemplo para sistema.'],
            ['key' => 'social_links', 'value' => json_encode(['facebook' => 'fb.com/empresa', 'instagram' => 'insta.com/empresa'])],
            ['key' => 'maintenance_mode', 'value' => 'false'],
            ['key' => 'logo', 'value' => '/storage/logo.png'],
            ['key' => 'favicon', 'value' => '/storage/favicon.ico'],
            ['key' => 'timezone', 'value' => 'Europe/Lisbon'],
            ['key' => 'time_expires_token', 'value' => '1'],
            ['key' => 'stripe_public_key', 'value' => ''],
            ['key' => 'stripe_secret_key', 'value' => ''],
            ['key' => 'stripe_webhook_secret', 'value' => 'whsec_1234567890abcdef'],
            ['key' => 'stripe_url', 'value' => 'https://api.stripe.com/v1'],
        ];
        DB::table('settings')->insert($settings);
    }
}
