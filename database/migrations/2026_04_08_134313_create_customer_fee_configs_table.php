<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_payment_method_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tpa_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_method');
            $table->string('label');
            $table->decimal('fee_percentage', 5, 4)->default(0);
            $table->decimal('fee_fixed', 8, 4)->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // null tpa_id = online (Stripe), non-null = TPA device
            $table->unique(['customer_id', 'tpa_id', 'payment_method', 'currency'], 'cpmf_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payment_method_fees');
    }
};
