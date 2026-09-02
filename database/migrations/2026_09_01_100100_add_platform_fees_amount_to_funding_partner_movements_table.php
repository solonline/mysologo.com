<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funding_partner_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('platform_fees_amount')->nullable()->after('fees_amount');
        });
    }

    public function down(): void
    {
        Schema::table('funding_partner_movements', function (Blueprint $table) {
            $table->dropColumn('platform_fees_amount');
        });
    }
};
