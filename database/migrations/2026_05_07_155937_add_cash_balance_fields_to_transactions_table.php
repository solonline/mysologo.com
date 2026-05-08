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
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('stripe_cash_balance_transaction_id')->nullable()->after('stripe_charge_id');
            $table->string('bank_transfer_type')->nullable()->after('stripe_metadata');
            $table->string('bank_transfer_reference')->nullable()->after('bank_transfer_type');
            $table->string('bank_transfer_sender_name')->nullable()->after('bank_transfer_reference');
            $table->string('bank_transfer_bic')->nullable()->after('bank_transfer_sender_name');
            $table->string('bank_transfer_iban_last4', 4)->nullable()->after('bank_transfer_bic');
            $table->integer('bank_transfer_amount')->nullable()->after('bank_transfer_iban_last4');
            $table->integer('bank_transfer_fee')->nullable()->after('bank_transfer_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_cash_balance_transaction_id',
                'bank_transfer_type',
                'bank_transfer_reference',
                'bank_transfer_sender_name',
                'bank_transfer_bic',
                'bank_transfer_iban_last4',
            ]);
        });
    }
};
