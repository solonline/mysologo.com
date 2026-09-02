<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_wallester_accounts', function (Blueprint $table) {
            $table->index('customer_id', 'cwa_customer_id_index');
        });

        Schema::table('customer_wallester_accounts', function (Blueprint $table) {
            $table->dropUnique('customer_wallester_accounts_customer_funding_currency_unique');
            $table->unique(
                ['customer_id', 'funding_currency', 'is_main'],
                'cwa_customer_currency_is_main_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('customer_wallester_accounts', function (Blueprint $table) {
            $table->dropUnique('cwa_customer_currency_is_main_unique');
            $table->unique(
                ['customer_id', 'funding_currency'],
                'customer_wallester_accounts_customer_funding_currency_unique',
            );
        });

        Schema::table('customer_wallester_accounts', function (Blueprint $table) {
            $table->dropIndex('cwa_customer_id_index');
        });
    }
};
