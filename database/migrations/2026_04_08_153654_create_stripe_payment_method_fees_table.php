<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_payment_method_fees', function (Blueprint $table) {
            $table->id();
            $table->string('payment_method');
            $table->string('label');
            $table->decimal('stripe_fee_percentage', 5, 4)->default(0);
            $table->decimal('stripe_fee_fixed', 8, 4)->default(0);
            $table->decimal('platform_fee_percentage', 5, 4)->default(0);
            $table->decimal('platform_fee_fixed', 8, 4)->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['payment_method', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_payment_method_fees');
    }
};
