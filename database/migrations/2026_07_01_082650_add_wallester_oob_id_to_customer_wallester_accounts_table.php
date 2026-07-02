<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('customers', 'wallester_oob_user_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('wallester_oob_user_id');
            });
        }

        Schema::table('customer_wallester_accounts', function (Blueprint $table) {
            $table->uuid('wallester_oob_id')->nullable()->unique()->after('wallester_account_id');
        });

        DB::table('customer_wallester_accounts')
            ->whereNull('wallester_oob_id')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $account): void {
                DB::table('customer_wallester_accounts')
                    ->where('id', $account->id)
                    ->update(['wallester_oob_id' => (string) Str::uuid()]);
            });
    }

    public function down(): void
    {
        Schema::table('customer_wallester_accounts', function (Blueprint $table) {
            $table->dropColumn('wallester_oob_id');
        });
    }
};
