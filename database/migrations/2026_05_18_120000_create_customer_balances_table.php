<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->char('currency', 3);
            $table->bigInteger('available')->default(0);
            $table->bigInteger('pending')->default(0);
            $table->timestamps();

            $table->unique(['customer_id', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_balances');
    }
};
