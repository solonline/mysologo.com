<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_payment_method_fees', function (Blueprint $table) {
            $table->foreignId('merchant_id')
                ->nullable()
                ->after('tpa_id')
                ->constrained()
                ->nullOnDelete();

            // Nullable FKs are distinct in UNIQUE indexes, so TPA and merchant
            // uniqueness each need their own key (the other side is always null).
            $table->unique(
                ['customer_id', 'merchant_id', 'payment_method', 'currency'],
                'cpmf_merchant_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('customer_payment_method_fees', function (Blueprint $table) {
            $table->dropUnique('cpmf_merchant_unique');
            $table->dropConstrainedForeignId('merchant_id');
        });
    }
};
