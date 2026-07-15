<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_payment_intents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_payment_intent_id')->unique();
            $table->foreignId('stripe_payment_method_fee_id')->nullable()->constrained('stripe_payment_method_fees')->nullOnDelete();
            $table->foreignId('tpa_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stripe_transaction_id')->nullable()->constrained('stripe_transactions')->nullOnDelete();

            $table->string('type')->index();
            $table->string('purpose')->index();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('EUR');
            $table->string('payment_method_type')->nullable();
            $table->string('status')->default('requires_payment_method')->index();

            $table->text('description')->nullable();
            $table->json('stripe_metadata')->nullable();
            $table->string('stripe_cash_balance_transaction_id')->nullable()->unique();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('succeeded_at')->nullable();
            $table->timestamp('stripe_created_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_payment_intents');
    }
};
