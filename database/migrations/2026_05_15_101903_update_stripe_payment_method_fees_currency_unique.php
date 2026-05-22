<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('stripe_payment_method_fees')
            ->whereNull('currency')
            ->update(['currency' => 'EUR']);

        Schema::table('stripe_payment_method_fees', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('stripe_payment_method_fees'))
                ->pluck('name');

            if ($indexes->contains('stripe_payment_method_fees_payment_method_unique')) {
                $table->dropUnique(['payment_method']);
            }

            $table->string('currency', 3)->nullable(false)->change();

            if (! $indexes->contains('stripe_payment_method_fees_payment_method_currency_unique')) {
                $table->unique(['payment_method', 'currency']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('stripe_payment_method_fees', function (Blueprint $table) {
            $table->dropUnique(['payment_method', 'currency']);

            $table->string('currency', 3)->nullable()->change();

            $table->unique(['payment_method']);
        });
    }
};
