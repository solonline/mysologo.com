<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ambientes que ainda tenham o unique antigo (mesmo aparelho / várias contas).
        if (Schema::hasIndex('customer_devices', 'customer_devices_push_token_unique')) {
            Schema::table('customer_devices', function (Blueprint $table) {
                $table->dropUnique(['push_token']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('customer_devices', function (Blueprint $table) {
            $table->unique('push_token');
        });
    }
};
