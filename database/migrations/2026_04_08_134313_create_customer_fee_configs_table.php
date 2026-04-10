<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_fee_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tpa_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_tpa')->default(false);
            $table->decimal('system_fee_rate', )->default(0.00)->comment('taxa aplicada na saída');
            $table->timestamps();

            // um customer só tem uma config normal, mas N configs TPA (uma por TPA)
            $table->unique(['customer_id', 'tpa_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_fee_configs');
    }
};
