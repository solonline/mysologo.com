<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_payment_requests', function (Blueprint $table) {
            $table->json('payment_method_types')->nullable()->after('items');
        });
    }

    public function down(): void
    {
        Schema::table('merchant_payment_requests', function (Blueprint $table) {
            $table->dropColumn('payment_method_types');
        });
    }
};
