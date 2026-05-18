<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE customers MODIFY COLUMN type ENUM('retail', 'corporate', 'responsible', 'platform') NOT NULL DEFAULT 'retail'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE customers MODIFY COLUMN type ENUM('retail', 'corporate', 'responsible') NOT NULL DEFAULT 'retail'");
    }
};
