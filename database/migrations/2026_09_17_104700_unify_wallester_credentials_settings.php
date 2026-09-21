<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private const CREDENTIAL_SUFFIXES = ['api_key', 'private_key', 'product_code'];

    /** @var array<string, array{label: string, type: string, env: string}> */
    private const GLOBAL_CREDENTIALS = [
        'api_key' => [
            'label' => 'API Key',
            'type' => 'secret',
            'env' => 'WALLESTER_API_KEY',
        ],
        'private_key' => [
            'label' => 'Private Key (PEM)',
            'type' => 'textarea',
            'env' => 'WALLESTER_PRIVATE_KEY',
        ],
        'product_code' => [
            'label' => 'Product Code',
            'type' => 'text',
            'env' => 'WALLESTER_PRODUCT_CODE',
        ],
    ];

    /** @var array<string, string> */
    private const ACCOUNT_LABELS = [
        'wallester.eur.platform_master_account_id' => 'Platform Master Account ID (EUR)',
        'wallester.eur.platform_fee_account_id' => 'Sub Conta Master (lucro) (EUR)',
        'wallester.usd.platform_master_account_id' => 'Platform Master Account ID (USD)',
        'wallester.usd.platform_fee_account_id' => 'Sub Conta Master (taxas) (USD)',
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::GLOBAL_CREDENTIALS as $suffix => $meta) {
            $key = "wallester.{$suffix}";
            $value = $this->firstCredentialValue($suffix, $meta['env']);
            $existing = DB::table('settings')->where('key', $key)->first();

            if ($existing === null) {
                DB::table('settings')->insert([
                    'key' => $key,
                    'label' => $meta['label'],
                    'type' => $meta['type'],
                    'value' => $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                continue;
            }

            if (trim((string) $existing->value) === '' && $value !== '') {
                DB::table('settings')->where('key', $key)->update([
                    'value' => $value,
                    'updated_at' => $now,
                ]);
            }
        }

        DB::table('settings')
            ->whereIn('key', $this->legacyCredentialKeys())
            ->delete();

        foreach (self::ACCOUNT_LABELS as $key => $label) {
            DB::table('settings')->where('key', $key)->update([
                'label' => $label,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $now = now();

        foreach (self::CREDENTIAL_SUFFIXES as $suffix) {
            $globalValue = (string) (DB::table('settings')->where('key', "wallester.{$suffix}")->value('value') ?? '');
            $meta = self::GLOBAL_CREDENTIALS[$suffix];

            foreach (['eur', 'usd'] as $currency) {
                $key = "wallester.{$currency}.{$suffix}";

                if (DB::table('settings')->where('key', $key)->exists()) {
                    continue;
                }

                DB::table('settings')->insert([
                    'key' => $key,
                    'label' => $meta['label'],
                    'type' => $meta['type'],
                    'value' => $globalValue,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        DB::table('settings')->whereIn('key', [
            'wallester.api_key',
            'wallester.private_key',
            'wallester.product_code',
        ])->delete();
    }

    private function firstCredentialValue(string $suffix, string $envKey): string
    {
        foreach ([
            DB::table('settings')->where('key', "wallester.eur.{$suffix}")->value('value'),
            DB::table('settings')->where('key', "wallester.usd.{$suffix}")->value('value'),
            env($envKey, ''),
        ] as $value) {
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return '';
    }

    /** @return list<string> */
    private function legacyCredentialKeys(): array
    {
        $keys = [];

        foreach (['eur', 'usd'] as $currency) {
            foreach (self::CREDENTIAL_SUFFIXES as $suffix) {
                $keys[] = "wallester.{$currency}.{$suffix}";
            }
        }

        return $keys;
    }
};
