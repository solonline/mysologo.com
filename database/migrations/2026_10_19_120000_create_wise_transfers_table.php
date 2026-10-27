<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wise_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->unsignedBigInteger('wise_transfer_id')->unique();
            $table->uuid('wise_batch_group_id');
            $table->unsignedBigInteger('wise_recipient_id');
            $table->string('beneficiary_name');
            $table->string('reference');
            $table->string('currency', 3);
            $table->unsignedInteger('amount_cents');
            $table->unsignedInteger('fee_cents')->nullable();
            $table->string('fee_currency', 3)->nullable();
            $table->string('idempotency_key')->unique();
            $table->string('status', 64);
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('receipt_sent_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index('wise_batch_group_id', 'wise_transfers_batch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wise_transfers');
    }
};
