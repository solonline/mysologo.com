<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'didit_user_id')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['didit_user_id']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('didit_user_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'didit_user_id')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->string('didit_user_id', 100)->nullable()->unique()->after('referal_email_code');
        });
    }
};
