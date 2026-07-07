<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_wallester_accounts', function (Blueprint $table) {
            $table->string('funding_currency', 3)->nullable()->after('currency_code');
        });

        DB::table('customer_wallester_accounts')->orderBy('id')->each(function (object $row): void {
            $code = strtoupper((string) $row->currency_code);
            $fundingCurrency = match (true) {
                str_starts_with($code, 'EUR') => 'EUR',
                str_starts_with($code, 'USD') => 'USD',
                strlen($code) === 3 => $code,
                default => 'EUR',
            };

            DB::table('customer_wallester_accounts')
                ->where('id', $row->id)
                ->update(['funding_currency' => $fundingCurrency]);
        });

        Schema::table('customer_wallester_accounts', function (Blueprint $table) {
            $table->dropUnique(['currency_code']);
            $table->dropUnique(['product_id']);
            $table->dropUnique(['company_id']);
        });

        Schema::table('customer_wallester_accounts', function (Blueprint $table) {
            $table->unique(['customer_id', 'funding_currency'], 'customer_wallester_accounts_customer_funding_currency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('customer_wallester_accounts', function (Blueprint $table) {
            $table->dropUnique('customer_wallester_accounts_customer_funding_currency_unique');
            $table->unique('currency_code');
            $table->unique('product_id');
            $table->unique('company_id');
            $table->dropColumn('funding_currency');
        });
    }
};
