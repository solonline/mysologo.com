<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stripe_transactions', function (Blueprint $table) {
            $table->string('partner_funding_status')->nullable()->after('balance_settled_at');
            $table->text('partner_funding_failure_reason')->nullable()->after('partner_funding_status');
            $table->timestamp('partner_funded_at')->nullable()->after('partner_funding_failure_reason');

            $table->index('partner_funding_status');
        });
    }

    public function down(): void
    {
        Schema::table('stripe_transactions', function (Blueprint $table) {
            $table->dropIndex(['partner_funding_status']);
            $table->dropColumn([
                'partner_funding_status',
                'partner_funding_failure_reason',
                'partner_funded_at',
            ]);
        });
    }
};
