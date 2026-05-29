<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tpa_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stripe_payment_method_fee_id')->nullable()->constrained('stripe_payment_method_fees')->nullOnDelete();
            $table->foreignId('related_transaction_id')->nullable()->constrained('stripe_transactions')->nullOnDelete();

            $table->string('type')->default('stripe')->index();
            $table->string('direction')->default('credit');

            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->string('stripe_charge_id')->nullable()->index();
            $table->string('stripe_cash_balance_transaction_id')->nullable();

            $table->string('payment_method_type')->nullable();

            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('stripe_fee');
            $table->unsignedBigInteger('platform_fee');
            $table->unsignedBigInteger('net_amount');

            $table->string('currency', 3)->default('EUR');
            $table->string('stripe_account_currency', 3)->nullable();
            $table->enum('status', ['pending', 'succeeded', 'failed', 'refunded', 'canceled'])->default('pending');

            $table->text('description')->nullable();
            $table->json('stripe_metadata')->nullable();

            $table->timestamp('balance_settled_at')->nullable();
            $table->string('ebury_transfer_id')->nullable()->index();
            $table->timestamp('ebury_settled_at')->nullable();

            $table->timestamp('stripe_created_at')->nullable();
            $table->timestamp('settles_at')->nullable();
            $table->date('available_on')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_transactions');
    }
};
