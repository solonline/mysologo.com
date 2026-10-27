<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_balances', function (Blueprint $table) {
            $table->renameColumn('available', 'wallester_allocated');
        });
    }

    public function down(): void
    {
        Schema::table('customer_balances', function (Blueprint $table) {
            $table->renameColumn('wallester_allocated', 'available');
        });
    }
};
