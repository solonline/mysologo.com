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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tpa_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stripe_payment_method_fee_id')->nullable()->constrained('stripe_payment_method_fees')->nullOnDelete();
            $table->foreignId('related_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();

            // Tipo e direção da transação (string para permitir novos tipos sem ALTER TABLE)
            $table->string('type')->default('stripe')->index();
            $table->string('direction')->default('credit');

            // Referências do Stripe (apenas para type=stripe)
            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->string('stripe_charge_id')->nullable()->index();

            // Método de pagamento (ex: 'card', 'mb_way', 'sepa_debit' — null para p2p)
            $table->string('payment_method_type')->nullable();

            // Valores em centavos (para evitar arredondamento)
            $table->unsignedBigInteger('amount');           // valor bruto
            $table->unsignedBigInteger('stripe_fee');       // taxa do Stripe (calculada)
            $table->unsignedBigInteger('platform_fee');     // taxa da plataforma (calculada)
            $table->unsignedBigInteger('net_amount');       // valor líquido = amount - stripe_fee - platform_fee

            $table->string('currency', 3)->default('EUR');
            $table->enum('status', ['pending', 'succeeded', 'failed', 'refunded', 'canceled'])->default('pending');

            $table->text('description')->nullable();
            $table->json('stripe_metadata')->nullable();

            $table->timestamp('stripe_created_at')->nullable();
            $table->timestamp('settles_at')->nullable(); // stripe_created_at + T+4 dias úteis
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'status']);
        });
    }
  
    /**
    * Reverse the migrations.
    */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
