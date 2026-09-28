<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_devices', function (Blueprint $table) {
            $table->string('push_token', 512)
                ->nullable()
                ->unique()
                ->comment('Token FCM deste aparelho. Guardado em claro porque o envio precisa do valor exato.');

            $table->string('platform', 16)
                ->nullable()
                ->comment('Origem do aparelho: ios, android ou web.');

            $table->timestamp('push_token_seen_at')
                ->nullable()
                ->comment('Última vez que a app renovou o token FCM.');

            $table->boolean('push_enabled')
                ->default(false)
                ->comment('Indica se este aparelho pode receber push.');
        });
    }

    public function down(): void
    {
        Schema::table('customer_devices', function (Blueprint $table) {
            $table->dropUnique(['push_token']);
            $table->dropColumn([
                'push_token',
                'platform',
                'push_token_seen_at',
                'push_enabled',
            ]);
        });
    }
};
