<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stripe_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('immediate_gross_amount')->nullable()->after('net_amount');
            $table->unsignedBigInteger('wallester_funding_net_amount')->nullable()->after('immediate_gross_amount');
            $table->unsignedBigInteger('stripe_deferred_net_amount')->nullable()->after('wallester_funding_net_amount');
        });
    }

    public function down(): void
    {
        Schema::table('stripe_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'immediate_gross_amount',
                'wallester_funding_net_amount',
                'stripe_deferred_net_amount',
            ]);
        });
    }
};
