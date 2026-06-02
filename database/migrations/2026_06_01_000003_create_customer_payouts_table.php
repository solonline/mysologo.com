<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_payouts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('settlement_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);

            // Snapshot do IBAN no momento do payout
            $table->string('iban')->nullable();
            $table->string('bic')->nullable();
            $table->string('account_holder_name')->nullable();

            // Referência no sistema do terceiro
            $table->string('external_transfer_id')->nullable()->index();
            $table->timestamp('processed_at')->nullable();

            $table->string('status')->default('pending');
            $table->text('failure_reason')->nullable();

            $table->timestamps();

            $table->index(['settlement_batch_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->unique(['settlement_batch_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payouts');
    }
};
