<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stripe_payment_method_fees', function (Blueprint $table) {
            $table->id();

            // Identificador do método de pagamento no Stripe (ex: 'card', 'mb_way', 'sepa_debit')
            $table->string('payment_method')->unique();
            $table->string('label');  // Nome amigável (ex: 'Cartão EEA', 'MB WAY')

            // Taxa base do Stripe
            $table->decimal('stripe_fee_percentage', 5, 4)->default(0); // ex: 0.0150 = 1.50%
            $table->decimal('stripe_fee_fixed', 8, 4)->default(0);      // ex: 0.25 = €0.25

            // Taxa adicional cobrada pela nossa plataforma
            $table->decimal('platform_fee_percentage', 5, 4)->default(0);
            $table->decimal('platform_fee_fixed', 8, 4)->default(0);

            $table->string('currency', 3)->default('EUR');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stripe_payment_method_fees');
    }
};
