<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_payment_requests', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignId('payer_id')->nullable()->constrained('payers')->nullOnDelete();

            // Preenchido quando o pagamento é confirmado via webhook Stripe
            $table->foreignId('transaction_id')->nullable()->constrained('stripe_transactions')->nullOnDelete();
            $table->string('stripe_payment_intent_id')->nullable()->index();

            $table->unsignedBigInteger('amount');
            $table->string('currency', 3);
            $table->text('description')->nullable();

            // Referência do lado do merchant (ex: order_id deles) — único por merchant
            $table->string('external_reference')->nullable();

            // URL de retorno por request; se null usa o webhook_url do merchant
            $table->string('callback_url')->nullable();

            //Langua para a interface de pagamento (ex: 'pt', 'en', 'es'); se null usa o default do merchant ou detecta pelo navegador
            $table->string('language', 5)->nullable();

            // Payload livre para o merchant correlacionar com os seus sistemas
            $table->json('metadata')->nullable();

            $table->json('items')->nullable();

            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'expired', 'cancelled'])
                ->default('pending');

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->unique(['merchant_id', 'external_reference']);
            $table->index(['uuid', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_payment_requests');
    }
};
