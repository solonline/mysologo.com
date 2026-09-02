<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('settings')->where('key', 'fx.percentage')->exists()) {
            return;
        }

        $now = now();

        DB::table('settings')->insert([
            'key' => 'fx.percentage',
            'label' => 'Percentagem da plataforma',
            'type' => 'text',
            'value' => env('FX_PERCENTAGE', ''),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'fx.percentage')->delete();
    }
};
