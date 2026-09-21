<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $key = 'wallester.rsa_public_key';

        if (DB::table('settings')->where('key', $key)->exists()) {
            return;
        }

        $now = now();

        DB::table('settings')->insert([
            'key' => $key,
            'label' => 'Wallester RSA Public Key (PEM)',
            'type' => 'textarea',
            'value' => env('WALLESTER_RSA_PUBLIC_KEY', ''),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'wallester.rsa_public_key')->delete();
    }
};
