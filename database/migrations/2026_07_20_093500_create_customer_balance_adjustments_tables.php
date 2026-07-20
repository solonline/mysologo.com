<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_balance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 16);
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3);
            $table->string('description');
            $table->string('status', 32);
            $table->unsignedTinyInteger('required_approvals')->default(1);
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('idempotency_key')->unique();
            $table->string('partner_external_id')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });

        Schema::create('customer_balance_adjustment_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adjustment_id')
                ->constrained('customer_balance_adjustments')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 6);
            $table->timestamp('code_expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('sms_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['adjustment_id', 'user_id'], 'cba_approvals_adjustment_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_balance_adjustment_approvals');
        Schema::dropIfExists('customer_balance_adjustments');
    }
};
