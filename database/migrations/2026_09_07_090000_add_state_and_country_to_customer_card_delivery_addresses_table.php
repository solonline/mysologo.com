<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_card_delivery_addresses', function (Blueprint $table) {
            $table->string('state')->nullable()->after('city');
            $table->string('country', 2)->nullable()->after('state');
        });

        DB::table('customer_card_delivery_addresses')
            ->whereNull('country')
            ->update(['country' => 'PT']);
    }

    public function down(): void
    {
        Schema::table('customer_card_delivery_addresses', function (Blueprint $table) {
            $table->dropColumn(['state', 'country']);
        });
    }
};
