<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['customer_stripe_accounts', 'customer_wallester_accounts', 'customer_ebury_accounts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('status_reason')->nullable()->after('status');
                $table->string('status_source', 50)->nullable()->after('status_reason');
                $table->timestamp('status_changed_at')->nullable()->after('status_source');
            });
        }
    }

    public function down(): void
    {
        foreach (['customer_stripe_accounts', 'customer_wallester_accounts', 'customer_ebury_accounts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['status_reason', 'status_source', 'status_changed_at']);
            });
        }
    }
};
