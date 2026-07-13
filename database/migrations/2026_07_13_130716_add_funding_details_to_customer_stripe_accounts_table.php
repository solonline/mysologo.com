<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_stripe_accounts', function (Blueprint $table) {
            $table->string('funding_status')->default('PENDING')->after('status_changed_at');
            $table->text('funding_status_reason')->nullable()->after('funding_status');
            $table->timestamp('funding_status_changed_at')->nullable()->after('funding_status_reason');
            $table->string('viban')->nullable()->after('funding_status_changed_at');
            $table->string('funding_type')->nullable()->after('viban');
            $table->string('bank_transfer_type')->nullable()->after('funding_type');
            $table->string('funding_iban')->nullable()->after('bank_transfer_type');
            $table->string('funding_bic')->nullable()->after('funding_iban');
            $table->string('funding_account_holder_name')->nullable()->after('funding_bic');
            $table->string('funding_country', 2)->nullable()->after('funding_account_holder_name');
            $table->json('funding_financial_addresses')->nullable()->after('funding_country');

            $table->index('funding_status');
        });
    }

    public function down(): void
    {
        Schema::table('customer_stripe_accounts', function (Blueprint $table) {
            $table->dropIndex(['funding_status']);
            $table->dropColumn([
                'funding_status',
                'funding_status_reason',
                'funding_status_changed_at',
                'viban',
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
