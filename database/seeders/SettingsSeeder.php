<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    private const DEFAULT_WALLESTER_MASTER_EUR = '75d92dc4-6de9-4580-8b62-3cbd3513af56';

    private const DEFAULT_WALLESTER_FEE_EUR = '626c80a3-f8e7-4627-8bcf-ca73f9944abf';

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
            // Email SMTP (emails enviados aos clientes)
            ['key' => 'mail.host',          'label' => 'Host SMTP',        'type' => 'text',     'value' => env('MAIL_HOST', '')],
            ['key' => 'mail.port',          'label' => 'Porta SMTP',       'type' => 'number',   'value' => (string) env('MAIL_PORT', '587')],
            ['key' => 'mail.encryption',    'label' => 'Encriptação',      'type' => 'select',   'value' => env('MAIL_ENCRYPTION', 'tls')],
            ['key' => 'mail.username',      'label' => 'Utilizador SMTP',  'type' => 'text',     'value' => env('MAIL_USERNAME', '')],
            ['key' => 'mail.password',      'label' => 'Palavra-passe SMTP', 'type' => 'secret', 'value' => env('MAIL_PASSWORD', '')],
            ['key' => 'mail.from_address',  'label' => 'Email remetente',  'type' => 'email',    'value' => env('MAIL_FROM_ADDRESS', '')],
            ['key' => 'mail.from_name',     'label' => 'Nome remetente',   'type' => 'text',     'value' => env('MAIL_FROM_NAME', env('APP_NAME', 'Yoop'))],
            ['key' => 'mail.verify_peer',   'label' => 'Verificar certificado SSL', 'type' => 'toggle', 'value' => 'false'],
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
            // Wallester — global
            ['key' => 'wallester.base_url',                  'label' => 'Base URL',                    'type' => 'url',      'value' => env('WALLESTER_BASE_URL', 'https://api-frontend.wallester.com/v1/')],
            ['key' => 'wallester.audit_source_type',         'label' => 'Audit Source Type',           'type' => 'text',     'value' => env('WALLESTER_AUDIT_SOURCE_TYPE', 'Backend')],
            ['key' => 'wallester.card_rsa_private_key',    'label' => 'Card RSA Private Key (PEM)',  'type' => 'textarea', 'value' => env('WALLESTER_CARD_RSA_PRIVATE_KEY', '')],
            ['key' => 'wallester.3ds_challenge_ttl_seconds', 'label' => '3DS Challenge TTL (seconds)', 'type' => 'number', 'value' => (string) env('WALLESTER_3DS_CHALLENGE_TTL', 300)],
            ['key' => 'wallester.3ds_webhook_username',    'label' => '3DS Webhook Username',        'type' => 'secret',   'value' => ''],
            ['key' => 'wallester.3ds_webhook_password',    'label' => '3DS Webhook Password',        'type' => 'secret',   'value' => ''],
            ['key' => 'wallester.virtual_card_value',      'label' => 'Taxa do cartão virtual adicional (cêntimos)', 'type' => 'number', 'value' => (string) env('WALLESTER_VIRTUAL_CARD_VALUE', '0')],
            ['key' => 'wallester.physical_card_value',     'label' => 'Taxa do cartão físico adicional (cêntimos)',  'type' => 'number', 'value' => (string) env('WALLESTER_PHYSICAL_CARD_VALUE', '0')],
            // Wallester — EUR
            ['key' => 'wallester.eur.api_key',                     'label' => 'API Key',                     'type' => 'secret',   'value' => env('WALLESTER_API_KEY', '')],
            ['key' => 'wallester.eur.private_key',                 'label' => 'Private Key (PEM)',           'type' => 'textarea', 'value' => env('WALLESTER_PRIVATE_KEY', '')],
            ['key' => 'wallester.eur.product_code',                'label' => 'Product Code',                'type' => 'text',     'value' => env('WALLESTER_PRODUCT_CODE', '')],
            ['key' => 'wallester.eur.platform_master_account_id',  'label' => 'Platform Master Account ID',  'type' => 'text',     'value' => env('WALLESTER_PLATFORM_MASTER_EUR', env('WALLESTER_PLATFORM_MASTER_ACCOUNT_ID', self::DEFAULT_WALLESTER_MASTER_EUR))],
            ['key' => 'wallester.eur.platform_fee_account_id',     'label' => 'Sub Conta Master (lucro)',    'type' => 'text',     'value' => env('WALLESTER_PLATFORM_FEE_EUR', self::DEFAULT_WALLESTER_FEE_EUR)],
            // Wallester — USD
            ['key' => 'wallester.usd.api_key',                     'label' => 'API Key',                     'type' => 'secret',   'value' => env('WALLESTER_USD_API_KEY', '')],
            ['key' => 'wallester.usd.private_key',                 'label' => 'Private Key (PEM)',           'type' => 'textarea', 'value' => env('WALLESTER_USD_PRIVATE_KEY', '')],
            ['key' => 'wallester.usd.product_code',                'label' => 'Product Code',                'type' => 'text',     'value' => env('WALLESTER_USD_PRODUCT_CODE', '')],
            ['key' => 'wallester.usd.platform_master_account_id',  'label' => 'Platform Master Account ID',  'type' => 'text',     'value' => env('WALLESTER_PLATFORM_MASTER_USD', '')],
            ['key' => 'wallester.usd.platform_fee_account_id',     'label' => 'Sub Conta Master USD (taxas)', 'type' => 'text',    'value' => env('WALLESTER_PLATFORM_FEE_USD', '')],
            // Câmbio (AwesomeAPI hoje)
            ['key' => 'fx.base_url',           'label' => 'URL Base',                      'type' => 'url',    'value' => env('FX_BASE_URL', 'https://economia.awesomeapi.com.br')],
            ['key' => 'fx.api_key',            'label' => 'API Key (x-api-key)',           'type' => 'secret', 'value' => env('FX_API_KEY', '')],
            ['key' => 'fx.cache_ttl_seconds',  'label' => 'Cache TTL (segundos)',          'type' => 'number', 'value' => (string) env('FX_CACHE_TTL', '60')],
            ['key' => 'fx.percentage',         'label' => 'Percentagem da plataforma',    'type' => 'text',    'value' => env('FX_PERCENTAGE', '')],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, ['updated_at' => now(), 'created_at' => now()])
            );
        }
    }
}
