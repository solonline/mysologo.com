<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'show_profile_pic')) {
            return;
        }

        DB::table('customers')->update(['show_profile_pic' => false]);

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE customers MODIFY show_profile_pic TINYINT(1) NOT NULL DEFAULT 0');
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('customers', 'show_profile_pic')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE customers MODIFY show_profile_pic TINYINT(1) NOT NULL DEFAULT 1');
        }
    }
};
