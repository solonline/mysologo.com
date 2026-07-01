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
        Schema::table('customers', function (Blueprint $table) {
            $table->uuid('wallester_oob_user_id')->nullable()->unique()->after('id');
        });

        DB::table('customers')
            ->whereNull('wallester_oob_user_id')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $customer): void {
                DB::table('customers')
                    ->where('id', $customer->id)
                    ->update(['wallester_oob_user_id' => (string) Str::uuid()]);
            });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('wallester_oob_user_id');
        });
    }
};
