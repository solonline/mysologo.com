<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        if (DB::table('settings')->where('key', 'mail.verify_peer')->exists()) {
            return;
        }

        DB::table('settings')->insert([
            'key' => 'mail.verify_peer',
            'label' => 'Verificar certificado SSL',
            'type' => 'toggle',
            'value' => 'false',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'mail.verify_peer')->delete();
    }
};
