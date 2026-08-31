<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $settings = [
            ['key' => 'mail.host', 'label' => 'Host SMTP', 'type' => 'text', 'value' => env('MAIL_HOST', '')],
            ['key' => 'mail.port', 'label' => 'Porta SMTP', 'type' => 'number', 'value' => (string) env('MAIL_PORT', '587')],
            ['key' => 'mail.encryption', 'label' => 'Encriptação', 'type' => 'select', 'value' => env('MAIL_ENCRYPTION', 'tls')],
            ['key' => 'mail.username', 'label' => 'Utilizador SMTP', 'type' => 'text', 'value' => env('MAIL_USERNAME', '')],
            ['key' => 'mail.password', 'label' => 'Palavra-passe SMTP', 'type' => 'secret', 'value' => env('MAIL_PASSWORD', '')],
            ['key' => 'mail.from_address', 'label' => 'Email remetente', 'type' => 'email', 'value' => env('MAIL_FROM_ADDRESS', '')],
            ['key' => 'mail.from_name', 'label' => 'Nome remetente', 'type' => 'text', 'value' => env('MAIL_FROM_NAME', env('APP_NAME', 'Yoop'))],
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
            'mail.host',
            'mail.port',
            'mail.encryption',
            'mail.username',
            'mail.password',
            'mail.from_address',
            'mail.from_name',
        ])->delete();
    }
};
