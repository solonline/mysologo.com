<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private array $currencies = ['eur', 'usd', 'gbp', 'chf'];

    public function up(): void
    {
        $now = now();

        $liveLabels = [
            'public_key' => 'Produção — Chave pública (pk_)',
            'secret_key' => 'Produção — Chave secreta (sk_)',
            'webhook_secret' => 'Produção — Webhook secret (whsec_)',
        ];

        $testFields = [
            'test_public_key' => ['label' => 'Teste — Chave pública (pk_)', 'type' => 'text', 'from' => 'public_key', 'prefix' => 'pk_test_'],
            'test_secret_key' => ['label' => 'Teste — Chave secreta (sk_)', 'type' => 'secret', 'from' => 'secret_key', 'prefix' => 'sk_test_'],
            'test_webhook_secret' => ['label' => 'Teste — Webhook secret (whsec_)', 'type' => 'secret', 'from' => null, 'prefix' => null],
        ];

        foreach ($this->currencies as $currency) {
            foreach ($liveLabels as $field => $label) {
                DB::table('settings')
                    ->where('key', "stripe.{$currency}.{$field}")
                    ->update(['label' => $label, 'updated_at' => $now]);
            }

            foreach ($testFields as $field => $meta) {
                $key = "stripe.{$currency}.{$field}";
                if (DB::table('settings')->where('key', $key)->exists()) {
                    DB::table('settings')->where('key', $key)->update([
                        'label' => $meta['label'],
                        'type' => $meta['type'],
                        'updated_at' => $now,
                    ]);

                    continue;
                }

                DB::table('settings')->insert([
                    'key' => $key,
                    'label' => $meta['label'],
                    'type' => $meta['type'],
                    'value' => $this->copiedTestValue($currency, $meta['from'], $meta['prefix']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $keys = [];

        foreach ($this->currencies as $currency) {
            foreach (['test_public_key', 'test_secret_key', 'test_webhook_secret'] as $field) {
                $keys[] = "stripe.{$currency}.{$field}";
            }
        }

        DB::table('settings')->whereIn('key', $keys)->delete();
    }

    private function copiedTestValue(string $currency, ?string $liveField, ?string $prefix): string
    {
        if ($liveField === null || $prefix === null) {
            return '';
        }

        $value = DB::table('settings')->where('key', "stripe.{$currency}.{$liveField}")->value('value');

        if (! is_string($value) || ! str_starts_with($value, $prefix)) {
            return '';
        }

        return $value;
    }
};
