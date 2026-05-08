<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customer_stripe_accounts', function (Blueprint $table) {
            // funding_instructions top-level
            $table->string('funding_type')->nullable()->after('viban');               // "bank_transfer"
            $table->string('bank_transfer_type')->nullable()->after('funding_type'); // "eu_bank_transfer", "us_bank_transfer", etc.

            // financial address (IBAN / principal)
            $table->string('funding_iban')->nullable()->after('bank_transfer_type');
            $table->string('funding_bic')->nullable()->after('funding_iban');
            $table->string('funding_account_holder_name')->nullable()->after('funding_bic');
            $table->string('funding_country', 2)->nullable()->after('funding_account_holder_name');

            // payload completo para outros tipos (ABA, sort-code, etc.)
            $table->json('funding_financial_addresses')->nullable()->after('funding_country');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_stripe_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'funding_type',
                'bank_transfer_type',
                'funding_iban',
                'funding_bic',
                'funding_account_holder_name',
                'funding_country',
                'funding_financial_addresses',
            ]);
        });
    }
};
