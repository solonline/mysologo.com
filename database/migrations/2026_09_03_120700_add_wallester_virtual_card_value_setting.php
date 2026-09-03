<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $key = 'wallester.virtual_card_value';

        if (DB::table('settings')->where('key', $key)->exists()) {
            return;
        }

        $now = now();

        DB::table('settings')->insert([
            'key' => $key,
            'label' => 'Taxa do cartão virtual adicional (cêntimos)',
            'type' => 'number',
            'value' => (string) env('WALLESTER_VIRTUAL_CARD_VALUE', '0'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'wallester.virtual_card_value')->delete();
    }
};
