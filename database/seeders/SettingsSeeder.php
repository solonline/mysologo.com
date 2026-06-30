<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // Company
            ['key' => 'company.name',          'label' => 'Nome da empresa',        'type' => 'text',     'value' => 'Yoop'],
            ['key' => 'company.email',          'label' => 'Email de contacto',      'type' => 'email',    'value' => 'hello@yooppayments.eu'],
            ['key' => 'company.phone',          'label' => 'Telefone',               'type' => 'text',     'value' => '+351999999999'],
            ['key' => 'company.fiscal_id',      'label' => 'NIF / ID Fiscal',        'type' => 'text',     'value' => '123456789'],
            ['key' => 'company.website',        'label' => 'Website',                'type' => 'url',      'value' => 'https://yooppayments.eu'],
            ['key' => 'company.support_email',  'label' => 'Email de suporte',       'type' => 'email',    'value' => 'hello@yooppayments.eu'],
            ['key' => 'company.description',    'label' => 'Descrição',              'type' => 'textarea', 'value' => 'Plataforma de pagamento digital'],
            ['key' => 'company.logo',           'label' => 'Logo URL',               'type' => 'text',     'value' => '/storage/logo.png'],
            ['key' => 'company.favicon',        'label' => 'Favicon URL',            'type' => 'text',     'value' => '/storage/favicon.ico'],
            ['key' => 'company.social_links',   'label' => 'Redes Sociais (JSON)',   'type' => 'textarea', 'value' => json_encode(['facebook' => 'fb.com/empresa', 'instagram' => 'insta.com/yooppayments'])],
            // Address
            ['key' => 'address.street',         'label' => 'Morada',                 'type' => 'text',     'value' => 'Rua Exemplo, 123'],
            ['key' => 'address.city',           'label' => 'Cidade',                 'type' => 'text',     'value' => 'Lisboa'],
            ['key' => 'address.state',          'label' => 'Distrito',               'type' => 'text',     'value' => 'Lisboa'],
            ['key' => 'address.postal_code',    'label' => 'Código Postal',          'type' => 'text',     'value' => '1000-000'],
            ['key' => 'address.country',        'label' => 'País',                   'type' => 'text',     'value' => 'Portugal'],
            // System
            ['key' => 'system.timezone',            'label' => 'Timezone',                'type' => 'timezone', 'value' => 'Europe/Lisbon'],
            ['key' => 'system.time_expires_token',  'label' => 'Expiração token (horas)', 'type' => 'number',   'value' => '1'],
            ['key' => 'system.maintenance_mode',    'label' => 'Modo de manutenção',      'type' => 'toggle',   'value' => 'false'],
            // Stripe — por moeda (cada moeda tem a sua conta Stripe separada)
            // EUR
            ['key' => 'stripe.eur.public_key',     'label' => 'Chave Pública (pk_)',     'type' => 'text',   'value' => ''],
            ['key' => 'stripe.eur.secret_key',     'label' => 'Chave Secreta (sk_)',     'type' => 'secret', 'value' => ''],
            ['key' => 'stripe.eur.webhook_secret', 'label' => 'Webhook Secret (whsec_)', 'type' => 'secret', 'value' => 'whsec_1234567890abcdef'],
            // USD
            ['key' => 'stripe.usd.public_key',     'label' => 'Chave Pública (pk_)',     'type' => 'text',   'value' => ''],
            ['key' => 'stripe.usd.secret_key',     'label' => 'Chave Secreta (sk_)',     'type' => 'secret', 'value' => ''],
            ['key' => 'stripe.usd.webhook_secret', 'label' => 'Webhook Secret (whsec_)', 'type' => 'secret', 'value' => ''],
            // GBP
            ['key' => 'stripe.gbp.public_key',     'label' => 'Chave Pública (pk_)',     'type' => 'text',   'value' => ''],
            ['key' => 'stripe.gbp.secret_key',     'label' => 'Chave Secreta (sk_)',     'type' => 'secret', 'value' => ''],
            ['key' => 'stripe.gbp.webhook_secret', 'label' => 'Webhook Secret (whsec_)', 'type' => 'secret', 'value' => ''],
            // CHF
            ['key' => 'stripe.chf.public_key',     'label' => 'Chave Pública (pk_)',     'type' => 'text',   'value' => ''],
            ['key' => 'stripe.chf.secret_key',     'label' => 'Chave Secreta (sk_)',     'type' => 'secret', 'value' => ''],
            ['key' => 'stripe.chf.webhook_secret', 'label' => 'Webhook Secret (whsec_)', 'type' => 'secret', 'value' => ''],
            // sms
            ['key' => 'sms.base_url',    'label' => 'URL Base',   'type' => 'url',    'value' => 'https://messaging.gatewayapi.com'],
            ['key' => 'sms.auth_token',  'label' => 'Auth Token', 'type' => 'secret', 'value' => ''],
            ['key' => 'sms.key',    'label' => 'key',   'type' => 'text',   'value' => ''],
            ['key' => 'sms.secret',    'label' => 'Secret',   'type' => 'secret', 'value' => ''],
            // Didit (KYC/KYB)
            ['key' => 'didit.api_key',         'label' => 'API Key',              'type' => 'secret', 'value' => env('DIDIT_API_KEY', '')],
            ['key' => 'didit.api_secret',      'label' => 'API Secret',           'type' => 'secret', 'value' => env('DIDIT_API_SECRET', '')],
            ['key' => 'didit.base_url',        'label' => 'Base URL',             'type' => 'url',    'value' => env('DIDIT_BASE_URL', '')],
            ['key' => 'didit.workflow_id',     'label' => 'Workflow KYC (retail/responsible)', 'type' => 'text', 'value' => env('DIDIT_WORKFLOW_ID', '')],
            ['key' => 'didit.workflow_kyb_id', 'label' => 'Workflow KYB (corporate)',          'type' => 'text', 'value' => env('DIDIT_WORKFLOW_KYB_ID', '')],
            ['key' => 'didit.webhook_secret',  'label' => 'Webhook Secret',       'type' => 'secret', 'value' => env('DIDIT_WEBHOOK_SECRET', '')],
            // Social links
            ['key' => 'social.facebook',   'label' => 'Facebook URL',  'type' => 'url',    'value' => 'https://facebook.com/yooppayments'],
            ['key' => 'social.instagram',  'label' => 'Instagram URL', 'type' => 'url',    'value' => 'https://instagram.com/yooppayments'],
            // currencies
            ['key' => 'currency.accepted',  'label' => 'Accepted Currencies', 'type' => 'json',   'value' => json_encode(['EUR', 'USD', 'GBP', 'CHF'])],
            // Payment methods
            ['key' => 'payment_methods.enabled',  'label' => 'Enabled Payment Methods', 'type' => 'json',   'value' => json_encode(['card', 'card_non_eea', 'sepa_debit', 'mb_way', 'multibanco', 'bank_transfer'])],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, ['updated_at' => now(), 'created_at' => now()])
            );
        }
    }
}
