<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('merchant_payment_requests')) {
            return;
        }

        if (
            Schema::hasColumn('merchant_payment_requests', 'callback_url')
            && ! Schema::hasColumn('merchant_payment_requests', 'webhook_url')
        ) {
            Schema::table('merchant_payment_requests', function (Blueprint $table) {
                $table->renameColumn('callback_url', 'webhook_url');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('merchant_payment_requests')) {
            return;
        }

        if (
            Schema::hasColumn('merchant_payment_requests', 'webhook_url')
            && ! Schema::hasColumn('merchant_payment_requests', 'callback_url')
        ) {
            Schema::table('merchant_payment_requests', function (Blueprint $table) {
                $table->renameColumn('webhook_url', 'callback_url');
            });
        }
    }
};
